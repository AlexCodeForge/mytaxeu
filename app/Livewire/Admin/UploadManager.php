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
use Livewire\Component;
use Livewire\WithPagination;

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


    public function downloadUpload(int $uploadId)
    {
        $upload = Upload::findOrFail($uploadId);

        // Check if file is completed and exists
        if (!$upload->isCompleted()) {
            $this->dispatch('show-notification', [
                'message' => 'El archivo debe estar completado para poder descargarlo.',
                'type' => 'error',
            ]);
            return;
        }

        // Check if transformed file exists
        if (!$upload->hasTransformedFile()) {
            $this->dispatch('show-notification', [
                'message' => 'El archivo transformado no se encuentra disponible para descarga.',
                'type' => 'error',
            ]);
            return;
        }

        // Use the actual transformed filename (which already includes date and _transformado suffix)
        $transformedFilename = basename($upload->transformed_path);

        // Return download response for transformed file
        return Storage::disk($upload->disk)->download(
            $upload->transformed_path,
            $transformedFilename
        );
    }

    public function downloadOriginalFile(int $uploadId)
    {
        $upload = Upload::findOrFail($uploadId);

        if (!Storage::disk($upload->disk)->exists($upload->path)) {
            $this->dispatch('show-notification', [
                'message' => 'Archivo original no encontrado',
                'type' => 'error',
            ]);
            return;
        }

        return Storage::disk($upload->disk)->download($upload->path, $upload->original_name);
    }

    public function downloadTransformedFile(int $uploadId)
    {
        $upload = Upload::findOrFail($uploadId);

        if (!$upload->transformed_path || !Storage::disk($upload->disk)->exists($upload->transformed_path)) {
            $this->dispatch('show-notification', [
                'message' => 'Archivo transformado no encontrado',
                'type' => 'error',
            ]);
            return;
        }

        $filename = 'transformed_' . $upload->original_name;
        return Storage::disk($upload->disk)->download($upload->transformed_path, $filename);
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
