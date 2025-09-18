<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UploadManager extends Component
{
    use WithPagination;

    // Filter properties
    public string $statusFilter = '';
    public string $userFilter = '';
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $minFileSize = null; // MB
    public ?int $maxFileSize = null; // MB

    // Sorting properties
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // Pagination
    public int $perPage = 15;

    // Modal properties
    public bool $showDetailsModal = false;
    public ?Upload $selectedUpload = null;


    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'userFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'minFileSize' => ['except' => null],
        'maxFileSize' => ['except' => null],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 15],
    ];

    public function mount(): void
    {
        // Ensure user is admin
        Gate::authorize('viewAll', Upload::class);

        if (!Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedMinFileSize(): void
    {
        $this->resetPage();
    }

    public function updatedMaxFileSize(): void
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

    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->userFilter = '';
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->minFileSize = null;
        $this->maxFileSize = null;
        $this->resetPage();
    }

    public function showUploadDetails(int $uploadId): void
    {
        $this->selectedUpload = Upload::with(['user', 'uploadMetric'])->findOrFail($uploadId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal(): void
    {
        $this->showDetailsModal = false;
        $this->selectedUpload = null;
    }

    /**
     * Listen for upload status updates via events.
     */
    #[On('upload-status-changed')]
    public function onUploadStatusChanged(array $data): void
    {
        // Refresh the component to show updated status
        $this->dispatch('$refresh');
    }

    /**
     * Check for status change events and automatically refresh when needed
     */
    public function checkForStatusUpdates(): array
    {
        $allUploadIds = $this->getUploadsQuery()->pluck('id');
        $events = [];

        foreach ($allUploadIds as $uploadId) {
            $eventData = Cache::get("upload_status_event_{$uploadId}");
            if ($eventData) {
                $events[] = $eventData;
                Cache::forget("upload_status_event_{$uploadId}"); // Remove after reading
            }
        }

        // If we found any events, refresh the component
        if (!empty($events)) {
            $this->dispatch('$refresh');
        }

        return $events;
    }

    /**
     * Listen for job status updates from the job system.
     */
    #[On('echo:admin.jobs,job-status-updated')]
    public function onJobStatusUpdated(array $data): void
    {
        // Refresh the uploads list when job status changes
        $this->dispatch('$refresh');
    }

    /**
     * Listen for new upload creation.
     */
    #[On('upload-created')]
    public function onUploadCreated(array $data): void
    {
        // Reset to first page to show new upload
        $this->resetPage();
    }

    /**
     * Check if there are any active uploads that need monitoring.
     */
    public function hasActiveUploads(): bool
    {
        return $this->getUploadsQuery()
            ->whereIn('status', [Upload::STATUS_QUEUED, Upload::STATUS_PROCESSING])
            ->exists();
    }


    public function downloadUpload(int $uploadId)
    {
        try {
            Log::info("UploadManager: Starting download for upload ID: {$uploadId}");

            $upload = Upload::findOrFail($uploadId);
            Log::info("UploadManager: Found upload", [
                'id' => $upload->id,
                'status' => $upload->status,
                'original_name' => $upload->original_name,
                'transformed_path' => $upload->transformed_path,
                'disk' => $upload->disk,
            ]);

            // Check if file is completed and exists
            if (!$upload->isCompleted()) {
                Log::warning("UploadManager: Upload not completed", [
                    'upload_id' => $uploadId,
                    'status' => $upload->status,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'El archivo debe estar completado para poder descargarlo.',
                    'type' => 'error',
                ]);
                return;
            }

            Log::info("UploadManager: Upload is completed, checking for transformed file");

            // Check if transformed file exists
            if (!$upload->hasTransformedFile()) {
                Log::warning("UploadManager: No transformed file available", [
                    'upload_id' => $uploadId,
                    'transformed_path' => $upload->transformed_path,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'El archivo transformado no se encuentra disponible para descarga.',
                    'type' => 'error',
                ]);
                return;
            }

            Log::info("UploadManager: Transformed file available, checking disk existence");

            // Additional check to ensure file exists on disk
            $fileExists = Storage::disk($upload->disk)->exists($upload->transformed_path);
            Log::info("UploadManager: File existence check", [
                'transformed_path' => $upload->transformed_path,
                'disk' => $upload->disk,
                'exists' => $fileExists,
            ]);

            if (!$fileExists) {
                Log::error("UploadManager: Transformed file does not exist on disk", [
                    'upload_id' => $uploadId,
                    'transformed_path' => $upload->transformed_path,
                    'disk' => $upload->disk,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'El archivo transformado no existe en el disco.',
                    'type' => 'error',
                ]);
                return;
            }

            // Use the actual transformed filename (which already includes date and _transformado suffix)
            $transformedFilename = basename($upload->transformed_path);
            Log::info("UploadManager: Preparing download", [
                'transformed_filename' => $transformedFilename,
                'file_path' => $upload->transformed_path,
            ]);

            // Return download response for transformed file
            Log::info("UploadManager: Initiating file download");
            return Storage::disk($upload->disk)->download(
                $upload->transformed_path,
                $transformedFilename
            );

        } catch (\Exception $e) {
            Log::error("UploadManager: Download failed with exception", [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('show-notification', [
                'message' => 'Error al descargar el archivo: ' . $e->getMessage(),
                'type' => 'error',
            ]);
            return;
        }
    }

    public function downloadOriginalFile(int $uploadId)
    {
        try {
            Log::info("UploadManager: Starting original file download for upload ID: {$uploadId}");

            $upload = Upload::findOrFail($uploadId);
            Log::info("UploadManager: Found upload for original download", [
                'id' => $upload->id,
                'original_name' => $upload->original_name,
                'path' => $upload->path,
                'disk' => $upload->disk,
            ]);

            if (!Storage::disk($upload->disk)->exists($upload->path)) {
                Log::error("UploadManager: Original file does not exist", [
                    'upload_id' => $uploadId,
                    'path' => $upload->path,
                    'disk' => $upload->disk,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'Archivo original no encontrado',
                    'type' => 'error',
                ]);
                return;
            }

            Log::info("UploadManager: Initiating original file download", [
                'path' => $upload->path,
                'filename' => $upload->original_name,
            ]);

            return Storage::disk($upload->disk)->download($upload->path, $upload->original_name);

        } catch (\Exception $e) {
            Log::error("UploadManager: Original file download failed with exception", [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('show-notification', [
                'message' => 'Error al descargar el archivo original: ' . $e->getMessage(),
                'type' => 'error',
            ]);
            return;
        }
    }

    public function downloadTransformedFile(int $uploadId)
    {
        try {
            Log::info("UploadManager: Starting transformed file download for upload ID: {$uploadId}");

            $upload = Upload::findOrFail($uploadId);
            Log::info("UploadManager: Found upload for transformed download", [
                'id' => $upload->id,
                'original_name' => $upload->original_name,
                'transformed_path' => $upload->transformed_path,
                'disk' => $upload->disk,
            ]);

            if (!$upload->transformed_path) {
                Log::error("UploadManager: No transformed path set", [
                    'upload_id' => $uploadId,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'Archivo transformado no encontrado - ruta no establecida',
                    'type' => 'error',
                ]);
                return;
            }

            if (!Storage::disk($upload->disk)->exists($upload->transformed_path)) {
                Log::error("UploadManager: Transformed file does not exist on disk", [
                    'upload_id' => $uploadId,
                    'transformed_path' => $upload->transformed_path,
                    'disk' => $upload->disk,
                ]);
                $this->dispatch('show-notification', [
                    'message' => 'Archivo transformado no encontrado',
                    'type' => 'error',
                ]);
                return;
            }

            $filename = 'transformed_' . $upload->original_name;
            Log::info("UploadManager: Initiating transformed file download", [
                'transformed_path' => $upload->transformed_path,
                'filename' => $filename,
            ]);

            return Storage::disk($upload->disk)->download($upload->transformed_path, $filename);

        } catch (\Exception $e) {
            Log::error("UploadManager: Transformed file download failed with exception", [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('show-notification', [
                'message' => 'Error al descargar el archivo transformado: ' . $e->getMessage(),
                'type' => 'error',
            ]);
            return;
        }
    }




    public function getUploads(): LengthAwarePaginator
    {
        return $this->getUploadsQuery()->paginate($this->perPage);
    }

    private function getUploadsQuery(): Builder
    {
        return Upload::query()
            ->with(['user'])
            ->when($this->statusFilter, function (Builder $query): void {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->userFilter, function (Builder $query): void {
                $query->where('user_id', $this->userFilter);
            })
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $subQuery): void {
                    $subQuery->where('original_name', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function (Builder $userQuery): void {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('email', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->dateFrom, function (Builder $query): void {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function (Builder $query): void {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->when($this->minFileSize, function (Builder $query): void {
                $query->where('size_bytes', '>=', $this->minFileSize * 1024 * 1024);
            })
            ->when($this->maxFileSize, function (Builder $query): void {
                $query->where('size_bytes', '<=', $this->maxFileSize * 1024 * 1024);
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function getUsersProperty(): \Illuminate\Support\Collection
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereHas('uploads')
            ->orderBy('name')
            ->get();
    }

    public function getStatusCountsProperty(): array
    {
        $query = $this->getUploadsQuery();

        return [
            'all' => (clone $query)->count(),
            'received' => (clone $query)->where('status', Upload::STATUS_RECEIVED)->count(),
            'queued' => (clone $query)->where('status', Upload::STATUS_QUEUED)->count(),
            'processing' => (clone $query)->where('status', Upload::STATUS_PROCESSING)->count(),
            'completed' => (clone $query)->where('status', Upload::STATUS_COMPLETED)->count(),
            'failed' => (clone $query)->where('status', Upload::STATUS_FAILED)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.upload-manager', [
            'uploads' => $this->getUploads(),
            'users' => $this->users,
            'statusCounts' => $this->statusCounts,
        ])->layout('layouts.panel');
    }
}
