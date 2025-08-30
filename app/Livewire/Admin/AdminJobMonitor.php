<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\JobStatusService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AdminJobMonitor extends Component
{
    use WithPagination;

    public string $statusFilter = '';
    public string $userFilter = '';
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public bool $autoRefresh = true;
    public int $pollingInterval = 30; // seconds
    public string $selectedView = 'jobs'; // jobs, failed_jobs, stats

    // Modal properties
    public bool $showLogsModal = false;
    public ?int $selectedJobId = null;
    public array $selectedJobLogs = [];

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'userFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'selectedView' => ['except' => 'jobs'],
    ];

    /**
     * Initialize component and check admin permissions.
     */
    public function mount(): void
    {
        // Ensure user is admin
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->resetPage();
    }

    /**
     * Listen for job status updates.
     */
    #[On('job-status-updated')]
    public function jobStatusUpdated(array $data): void
    {
        // Refresh the admin dashboard when any job status changes
        $this->dispatch('$refresh');
    }

    /**
     * Listen for job log creation.
     */
    #[On('job-log-created')]
    public function jobLogCreated(array $data): void
    {
        // Refresh if we're viewing a specific job's logs
        $this->dispatch('$refresh');
    }

    /**
     * Switch between different views.
     */
    public function selectView(string $view): void
    {
        $this->selectedView = $view;
        $this->resetPage();
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
     * Filter jobs by user.
     */
    public function filterByUser(string $userId): void
    {
        $this->userFilter = $userId;
        $this->resetPage();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->statusFilter = '';
        $this->userFilter = '';
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    /**
     * Show job logs modal
     */
        public function showJobLogs(int $jobId): void
    {
        $this->selectedJobId = $jobId;
        $jobStatusService = app(JobStatusService::class);

        // Get logs as a paginated result and convert to collection
        $logsResult = $jobStatusService->getJobLogs($jobId, 100);
        $this->selectedJobLogs = collect($logsResult->items())->toArray();

        $this->showLogsModal = true;
    }

    /**
     * Close job logs modal
     */
    public function closeLogsModal(): void
    {
        $this->showLogsModal = false;
        $this->selectedJobId = null;
        $this->selectedJobLogs = [];
    }

    /**
     * Refresh data manually.
     */
    public function refreshData(): void
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
     * Export jobs to CSV.
     */
    public function exportToCsv(): void
    {
        $jobStatusService = app(JobStatusService::class);

        // Get all jobs matching current filters without pagination
        $jobs = $jobStatusService->getJobsForAdmin(
            status: $this->statusFilter ?: null,
            userId: $this->userFilter ? (int) $this->userFilter : null,
            search: $this->search ?: null,
            dateFrom: $this->dateFrom ?: null,
            dateTo: $this->dateTo ?: null,
            perPage: 10000 // Large number to get all results
        );

        // Here you would implement CSV export logic
        // For now, just show a notification
        $this->dispatch('show-notification', [
            'message' => 'Exportación CSV iniciada',
            'type' => 'info',
        ]);
    }

    /**
     * Retry a failed job.
     */
    public function retryFailedJob(string $failedJobUuid): void
    {
        try {
            $jobStatusService = app(JobStatusService::class);

            $success = $jobStatusService->retryFailedJob(
                failedJobUuid: $failedJobUuid,
                requestingUserId: Auth::id(),
                isAdmin: true
            );

            if ($success) {
                $this->dispatch('show-notification', [
                    'message' => 'Trabajo reintentado exitosamente',
                    'type' => 'success',
                ]);
                $this->refreshData();
            }
        } catch (\Exception $e) {
            $this->dispatch('show-notification', [
                'message' => 'Error al reintentar el trabajo: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    /**
     * Updated event handlers for filters.
     */
    public function updatedSearch(): void
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

    /**
     * Get jobs for admin monitoring.
     */
    public function getJobsProperty(): LengthAwarePaginator
    {
        $jobStatusService = app(JobStatusService::class);

        return $jobStatusService->getJobsForAdmin(
            status: $this->statusFilter ?: null,
            userId: $this->userFilter ? (int) $this->userFilter : null,
            search: $this->search ?: null,
            dateFrom: $this->dateFrom ?: null,
            dateTo: $this->dateTo ?: null,
            perPage: 20
        );
    }

    /**
     * Get failed jobs.
     */
    public function getFailedJobsProperty(): LengthAwarePaginator
    {
        $query = DB::table('failed_jobs')
            ->leftJoin('users', 'failed_jobs.user_id', '=', 'users.id')
            ->select([
                'failed_jobs.*',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        // Apply user filter
        if ($this->userFilter) {
            $query->where('failed_jobs.user_id', $this->userFilter);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $search = $this->search;
                $q->where('failed_jobs.file_name', 'LIKE', "%{$search}%")
                  ->orWhere('failed_jobs.uuid', 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                  ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        // Apply date filters
        if ($this->dateFrom) {
            $query->where('failed_jobs.failed_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('failed_jobs.failed_at', '<=', $this->dateTo . ' 23:59:59');
        }

        $query->orderBy('failed_jobs.failed_at', 'desc');

        return $query->paginate(20);
    }

    /**
     * Get overall job statistics.
     */
    public function getStatsProperty(): array
    {
        $jobStatusService = app(JobStatusService::class);

        return $jobStatusService->getJobStatistics();
    }

    /**
     * Get user list for filtering.
     */
    public function getUsersProperty(): \Illuminate\Support\Collection
    {
        return DB::table('users')
            ->select(['id', 'name', 'email'])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('jobs')
                      ->whereColumn('jobs.user_id', 'users.id');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get status counts for filter badges.
     */
    public function getStatusCountsProperty(): array
    {
        $stats = $this->stats;

        return [
            'all' => $stats['total_jobs'],
            'queued' => $stats['queued_jobs'],
            'processing' => $stats['processing_jobs'],
            'completed' => $stats['completed_jobs'],
            'failed' => $stats['failed_jobs'],
        ];
    }

    /**
     * Get recent activity for dashboard.
     */
    public function getRecentActivityProperty(): \Illuminate\Support\Collection
    {
        return DB::table('job_logs')
            ->leftJoin('jobs', 'job_logs.job_id', '=', 'jobs.id')
            ->leftJoin('users', 'jobs.user_id', '=', 'users.id')
            ->select([
                'job_logs.*',
                'jobs.file_name',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->orderBy('job_logs.created_at', 'desc')
            ->limit(10)
            ->get();
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
        return view('livewire.admin.admin-job-monitor', [
            'jobs' => $this->jobs,
            'failedJobs' => $this->failedJobs,
            'stats' => $this->stats,
            'users' => $this->users,
            'statusCounts' => $this->statusCounts,
            'recentActivity' => $this->recentActivity,
        ]);
    }
}
