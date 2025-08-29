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
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Upload::class);
    }

    public function updatedStatusFilter(): void
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
        $upload = Upload::findOrFail($uploadId);
        
        $this->authorize('delete', $upload);

        // Delete file from storage
        if ($upload->path && \Storage::disk($upload->disk)->exists($upload->path)) {
            \Storage::disk($upload->disk)->delete($upload->path);
        }

        $upload->delete();

        $this->dispatch('flash-message', [
            'type' => 'success',
            'message' => "Archivo '{$upload->original_name}' eliminado correctamente."
        ]);
    }

    #[On('upload-created')]
    public function refreshList(): void
    {
        // Refresh the uploads list when a new upload is created
        $this->resetPage();
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
        ]);
    }
}
