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
        $upload = Upload::findOrFail($uploadId);

        $this->authorize('download', $upload);

        // Check if file is completed and exists
        if (!$upload->isCompleted()) {
            $this->dispatch('flash-message', [
                'type' => 'error',
                'message' => 'El archivo debe estar completado para poder descargarlo.'
            ]);
            return;
        }

        // Check if transformed file exists
        if (!$upload->hasTransformedFile()) {
            $this->dispatch('flash-message', [
                'type' => 'error',
                'message' => 'El archivo transformado no se encuentra disponible para descarga.'
            ]);
            return;
        }

        // Use the actual transformed filename (which already includes date and _transformado suffix)
        $transformedFilename = basename($upload->transformed_path);

        // Return download response for transformed file
        return \Storage::disk($upload->disk)->download(
            $upload->transformed_path,
            $transformedFilename
        );
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
