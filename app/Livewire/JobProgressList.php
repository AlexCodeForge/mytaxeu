<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\JobStatusService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class JobProgressList extends Component
{
    use WithPagination;

    public string $statusFilter = '';
    public string $search = '';
    public bool $autoRefresh = true;
    public int $pollingInterval = 30; // seconds

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    /**
     * Initialize component.
     */
    public function mount(): void
    {
        $this->resetPage();
    }

    /**
     * Listen for job status updates.
     */
    #[On('job-status-updated')]
    public function jobStatusUpdated(array $data): void
    {
        // Check if this update is relevant to the current user
        if (isset($data['user_id']) && $data['user_id'] === Auth::id()) {
            // Refresh the job list without resetting pagination if possible
            $this->dispatch('$refresh');

            // Show toast notification for status changes
            $this->showStatusNotification($data);
        }
    }

    /**
     * Filter jobs by status.
     */
    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Refresh job list manually.
     */
    public function refreshJobs(): void
    {
        $this->dispatch('$refresh');
    }

    /**
     * Toggle auto-refresh functionality.
     */
    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    /**
     * Updated search filter.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Updated status filter.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Get jobs for the current user.
     */
    public function getJobsProperty(): LengthAwarePaginator
    {
        $jobStatusService = app(JobStatusService::class);

        return $jobStatusService->getJobsForUser(
            userId: Auth::id(),
            status: $this->statusFilter ?: null,
            search: $this->search ?: null,
            perPage: 10
        );
    }

    /**
     * Get job statistics for the current user.
     */
    public function getStatsProperty(): array
    {
        $jobStatusService = app(JobStatusService::class);

        return $jobStatusService->getJobStatistics(Auth::id());
    }

    /**
     * Get status counts for filter badges.
     */
    public function getStatusCountsProperty(): array
    {
        $jobStatusService = app(JobStatusService::class);
        $stats = $jobStatusService->getJobStatistics(Auth::id());

        return [
            'all' => $stats['total_jobs'],
            'queued' => $stats['queued_jobs'],
            'processing' => $stats['processing_jobs'],
            'completed' => $stats['completed_jobs'],
            'failed' => $stats['failed_jobs'],
        ];
    }

    /**
     * Show notification for status changes.
     */
    private function showStatusNotification(array $data): void
    {
        $status = $data['status'] ?? '';
        $fileName = $data['file_name'] ?? 'Unknown file';

        $message = match ($status) {
            'processing' => "Procesando: {$fileName}",
            'completed' => "Completado: {$fileName}",
            'failed' => "Fallido: {$fileName}",
            'queued' => "En cola: {$fileName}",
            default => "Estado actualizado: {$fileName}",
        };

        $type = match ($status) {
            'completed' => 'success',
            'failed' => 'error',
            'processing' => 'info',
            default => 'info',
        };

        $this->dispatch('show-notification', [
            'message' => $message,
            'type' => $type,
        ]);
    }

    /**
     * Get the status color for badges.
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'queued' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the status label in Spanish.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'queued' => 'En Cola',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            default => ucfirst($status),
        };
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.job-progress-list', [
            'jobs' => $this->jobs,
            'stats' => $this->stats,
            'statusCounts' => $this->statusCounts,
        ]);
    }
}
