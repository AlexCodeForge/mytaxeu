<?php

declare(strict_types=1);

namespace App\Livewire\Uploads;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Index extends Component
{
    use WithPagination, AuthorizesRequests;

    public string $statusFilter = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 15;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 15],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Upload::class);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function getUploads(): LengthAwarePaginator
    {
        return Upload::query()
            ->where('user_id', auth()->id())
            ->when($this->statusFilter, function (Builder $query): void {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function deleteUpload(int $uploadId): void
    {
        // Ensure only admins can delete uploads
        if (!auth()->user()->isAdmin()) {
            $this->dispatch('flash-message', [
                'type' => 'error',
                'message' => 'No tienes permisos para realizar esta acción.'
            ]);
            return;
        }

        $upload = Upload::findOrFail($uploadId);

        $this->authorize('delete', $upload);

        // Delete files from storage (both input and transformed files)
        if ($upload->path && \Storage::disk($upload->disk)->exists($upload->path)) {
            \Storage::disk($upload->disk)->delete($upload->path);
        }

        // Delete transformed file if it exists
        if ($upload->transformed_path && \Storage::disk($upload->disk)->exists($upload->transformed_path)) {
            \Storage::disk($upload->disk)->delete($upload->transformed_path);
        }

        $upload->delete();

        $this->dispatch('flash-message', [
            'type' => 'success',
            'message' => "Archivo '{$upload->original_name}' eliminado correctamente."
        ]);
    }

    public function downloadUpload(int $uploadId)
    {
        try {
            Log::info("USER DOWNLOAD: Starting download for upload ID: {$uploadId}", [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()->email,
            ]);
            
            $upload = Upload::findOrFail($uploadId);
            Log::info("USER DOWNLOAD: Found upload", [
                'upload_id' => $upload->id,
                'upload_user_id' => $upload->user_id,
                'current_user_id' => auth()->id(),
                'status' => $upload->status,
                'original_name' => $upload->original_name,
                'transformed_path' => $upload->transformed_path,
                'disk' => $upload->disk,
            ]);

            Log::info("USER DOWNLOAD: Attempting authorization check");
            $this->authorize('download', $upload);
            Log::info("USER DOWNLOAD: Authorization passed");

            // Check if file is completed and exists
            if (!$upload->isCompleted()) {
                Log::warning("USER DOWNLOAD: Upload not completed", [
                    'upload_id' => $uploadId,
                    'status' => $upload->status,
                ]);
                $this->dispatch('flash-message', [
                    'type' => 'error',
                    'message' => 'El archivo debe estar completado para poder descargarlo.'
                ]);
                return;
            }

            Log::info("USER DOWNLOAD: Upload is completed, checking for transformed file");

            // Check if transformed file exists
            if (!$upload->hasTransformedFile()) {
                Log::warning("USER DOWNLOAD: No transformed file available", [
                    'upload_id' => $uploadId,
                    'transformed_path' => $upload->transformed_path,
                    'hasTransformedFile' => $upload->hasTransformedFile(),
                ]);
                $this->dispatch('flash-message', [
                    'type' => 'error',
                    'message' => 'El archivo transformado no se encuentra disponible para descarga.'
                ]);
                return;
            }

            Log::info("USER DOWNLOAD: Transformed file available, checking disk existence");

            // Additional check to ensure file exists on disk
            $fileExists = \Storage::disk($upload->disk)->exists($upload->transformed_path);
            Log::info("USER DOWNLOAD: File existence check", [
                'transformed_path' => $upload->transformed_path,
                'disk' => $upload->disk,
                'exists' => $fileExists,
            ]);

            if (!$fileExists) {
                Log::error("USER DOWNLOAD: Transformed file does not exist on disk", [
                    'upload_id' => $uploadId,
                    'transformed_path' => $upload->transformed_path,
                    'disk' => $upload->disk,
                ]);
                $this->dispatch('flash-message', [
                    'type' => 'error',
                    'message' => 'El archivo transformado no existe en el disco.'
                ]);
                return;
            }

            // Use the actual transformed filename (which already includes date and _transformado suffix)
            $transformedFilename = basename($upload->transformed_path);
            Log::info("USER DOWNLOAD: Preparing download", [
                'transformed_filename' => $transformedFilename,
                'file_path' => $upload->transformed_path,
            ]);

            // Return download response for transformed file
            Log::info("USER DOWNLOAD: Initiating file download");
            return \Storage::disk($upload->disk)->download(
                $upload->transformed_path,
                $transformedFilename
            );

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::error("USER DOWNLOAD: Authorization failed", [
                'upload_id' => $uploadId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            
            $this->dispatch('flash-message', [
                'type' => 'error',
                'message' => 'No tienes permisos para descargar este archivo.'
            ]);
            return;
            
        } catch (\Exception $e) {
            Log::error("USER DOWNLOAD: Download failed with exception", [
                'upload_id' => $uploadId,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->dispatch('flash-message', [
                'type' => 'error',
                'message' => 'Error al descargar el archivo: ' . $e->getMessage()
            ]);
            return;
        }
    }

    #[On('upload-created')]
    public function refreshList(): void
    {
        // Refresh the uploads list when a new upload is created
        $this->resetPage();
    }

    /**
     * Listen for upload status changes and refresh automatically
     */
    #[On('upload-status-changed')]
    public function onUploadStatusChanged(array $data): void
    {
        // Check if this status change affects this user's uploads
        if (isset($data['userId']) && $data['userId'] === auth()->id()) {
            // Force component refresh to show updated status
            $this->dispatch('$refresh');
        }
    }

    /**
     * Check for status change events (for browser polling)
     */
    public function checkForStatusUpdates(): array
    {
        // Get current user's upload IDs (only recent ones to avoid unnecessary checks)
        $userUploads = Upload::where('user_id', auth()->id())
            ->where('created_at', '>=', now()->subDays(7)) // Only check uploads from last 7 days
            ->pluck('id');
        $events = [];
        $hasStatusChanges = false;

        foreach ($userUploads as $uploadId) {
            $eventData = Cache::get("upload_status_event_{$uploadId}");
            if ($eventData) {
                $events[] = $eventData;
                Cache::forget("upload_status_event_{$uploadId}"); // Remove after reading
                $hasStatusChanges = true;
            }
        }

        // If we found events, trigger a refresh
        if ($hasStatusChanges) {
            $this->dispatch('$refresh');
        }

        return $events;
    }



    public function getStatusCounts(): array
    {
        return Upload::query()
            ->where('user_id', auth()->id())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function render()
    {
        return view('livewire.uploads.index', [
            'uploads' => $this->getUploads(),
            'statusCounts' => $this->getStatusCounts(),
            'allStatuses' => Upload::STATUSES,
        ])->layout('layouts.panel');
    }
}
