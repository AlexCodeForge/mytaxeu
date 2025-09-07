<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Upload;
use App\Services\JobStatusService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    public bool $autoRefresh = false; // Disabled in favor of events
    public int $pollingInterval = 10; // Legacy - not used anymore
    public string $selectedView = 'jobs'; // jobs, failed_jobs, stats

    // Modal properties
    public bool $showLogsModal = false;
    public ?int $selectedJobId = null;
    public array $selectedJobLogs = [];

    // Confirm modal properties
    public bool $showConfirmModal = false;
    public string $confirmAction = '';
    public int|string $confirmTargetId = 0;
    public string $confirmTitle = '';
    public string $confirmMessage = '';
    public string $confirmButtonText = '';
    public string $confirmButtonColor = 'red';

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
     * Listen for job creation events.
     */
    #[On('job-created')]
    public function jobCreated(array $data): void
    {
        // Refresh the admin dashboard when a new job is created
        $this->dispatch('$refresh');
    }

    /**
     * Listen for upload events that create jobs.
     */
    #[On('upload-created')]
    public function uploadCreated(array $data): void
    {
        // Refresh when new uploads are created
        $this->dispatch('$refresh');
    }

    /**
     * Listen for upload status changes.
     */
    #[On('upload-status-changed')]
    public function uploadStatusChanged(array $data): void
    {
        // Refresh when upload status changes
        $this->dispatch('$refresh');
    }

    /**
     * Listen for upload cancellations.
     */
    #[On('upload-cancelled')]
    public function uploadCancelled(array $data): void
    {
        // Refresh when uploads are cancelled
        $this->dispatch('$refresh');
    }

    /**
     * Listen for upload deletions.
     */
    #[On('upload-deleted')]
    public function uploadDeleted(array $data): void
    {
        // Refresh when uploads are deleted
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
     * Show upload logs modal (Redis-compatible)
     */
    public function showJobLogs(int $uploadId): void
    {
        $this->selectedJobId = $uploadId;

        // For Redis queues, we don't have detailed logs, so show basic upload info
        $upload = Upload::with('user')->find($uploadId);

        if ($upload) {
            $this->selectedJobLogs = [
                [
                    'level' => 'info',
                    'message' => "Upload iniciado: {$upload->original_name}",
                    'created_at' => $upload->created_at,
                    'metadata' => json_encode([
                        'file_size' => $upload->formatted_size,
                        'line_count' => $upload->csv_line_count,
                        'user' => $upload->user->name,
                    ])
                ],
                [
                    'level' => $upload->status === Upload::STATUS_FAILED ? 'error' : 'info',
                    'message' => "Estado actual: {$upload->status_label}",
                    'created_at' => $upload->processed_at ?: $upload->updated_at,
                    'metadata' => $upload->failure_reason ? json_encode(['error' => $upload->failure_reason]) : null
                ]
            ];
        } else {
            $this->selectedJobLogs = [];
        }

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
     * Show confirm modal for dangerous actions
     */
    public function showConfirmModal(string $action, int|string $targetId, string $title, string $message, string $buttonText = 'Confirmar'): void
    {
        $this->confirmAction = $action;
        $this->confirmTargetId = $targetId;
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmButtonText = $buttonText;
        $this->confirmButtonColor = $action === 'delete' ? 'red' : 'yellow';
        $this->showConfirmModal = true;
    }

    /**
     * Close confirm modal
     */
    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->confirmAction = '';
        $this->confirmTargetId = '';
        $this->confirmTitle = '';
        $this->confirmMessage = '';
        $this->confirmButtonText = '';
        $this->confirmButtonColor = 'red';
    }

    /**
     * Execute confirmed action
     */
    public function executeConfirmedAction(): void
    {
        switch ($this->confirmAction) {
            case 'cancel':
                $this->cancelJob($this->confirmTargetId);
                break;
            case 'delete':
                $this->deleteJob($this->confirmTargetId);
                break;
            case 'delete-failed':
                $this->deleteFailedJob($this->confirmTargetId);
                break;
        }

        $this->closeConfirmModal();
    }

    /**
     * Show cancel confirmation modal
     */
    public function confirmCancel(int $uploadId): void
    {
        $this->showConfirmModal(
            'cancel',
            $uploadId,
            'Cancelar Upload',
            '¿Estás seguro de que quieres cancelar este upload?',
            'Cancelar Upload'
        );
    }

    /**
     * Show delete confirmation modal
     */
    public function confirmDelete(int $uploadId): void
    {
        $this->showConfirmModal(
            'delete',
            $uploadId,
            'Eliminar Upload',
            '¿Estás seguro de que quieres eliminar este upload? Esta acción no se puede deshacer.',
            'Eliminar Upload'
        );
    }

    /**
     * Show delete failed job confirmation modal
     */
    public function confirmDeleteFailed(string $failedJobUuid): void
    {
        $this->showConfirmModal(
            'delete-failed',
            $failedJobUuid,
            'Eliminar Trabajo Fallido',
            '¿Estás seguro de que quieres eliminar este trabajo fallido? Esta acción no se puede deshacer.',
            'Eliminar'
        );
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
     * Cancel an active upload (Redis-compatible).
     */
    public function cancelJob(int $uploadId): void
    {
        try {
            // Get the upload to check if it can be cancelled
            $upload = Upload::find($uploadId);

            if (!$upload) {
                $this->dispatch('show-notification', [
                    'message' => 'Upload no encontrado',
                    'type' => 'error',
                ]);
                return;
            }

            // Only allow cancelling queued uploads
            if ($upload->status !== Upload::STATUS_QUEUED) {
                $this->dispatch('show-notification', [
                    'message' => 'Solo se pueden cancelar uploads en cola',
                    'type' => 'warning',
                ]);
                return;
            }

            // Update upload status to cancelled (failed)
            $upload->update([
                'status' => Upload::STATUS_FAILED,
                'failure_reason' => 'Cancelado por administrador',
                'processed_at' => now(),
            ]);

            $this->dispatch('show-notification', [
                'message' => 'Upload cancelado exitosamente',
                'type' => 'success',
            ]);

            // Dispatch event to refresh other components
            $this->dispatch('upload-cancelled', ['uploadId' => $uploadId]);

        } catch (\Exception $e) {
            $this->dispatch('show-notification', [
                'message' => 'Error al cancelar el upload: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    /**
     * Delete a completed or failed upload from records (Redis-compatible).
     */
    public function deleteJob(int $uploadId): void
    {
        try {
            // Get the upload to check its status
            $upload = Upload::find($uploadId);

            if (!$upload) {
                $this->dispatch('show-notification', [
                    'message' => 'Upload no encontrado',
                    'type' => 'error',
                ]);
                return;
            }

            // Only allow deleting completed or failed uploads
            if (!in_array($upload->status, [Upload::STATUS_COMPLETED, Upload::STATUS_FAILED])) {
                $this->dispatch('show-notification', [
                    'message' => 'Solo se pueden eliminar uploads completados o fallidos',
                    'type' => 'warning',
                ]);
                return;
            }

            DB::beginTransaction();

            // Delete related files if they exist
            if ($upload->path && Storage::disk($upload->disk)->exists($upload->path)) {
                Storage::disk($upload->disk)->delete($upload->path);
            }
            if ($upload->transformed_path && Storage::disk($upload->disk)->exists($upload->transformed_path)) {
                Storage::disk($upload->disk)->delete($upload->transformed_path);
            }

            // Delete the upload record
            $upload->delete();

            DB::commit();

            $this->dispatch('show-notification', [
                'message' => 'Upload eliminado exitosamente',
                'type' => 'success',
            ]);

            // Dispatch event to refresh other components
            $this->dispatch('upload-deleted', ['uploadId' => $uploadId]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('show-notification', [
                'message' => 'Error al eliminar el upload: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    /**
     * Delete a failed job permanently.
     */
    public function deleteFailedJob(string $failedJobUuid): void
    {
        try {
            $deleted = DB::table('failed_jobs')
                ->where('uuid', $failedJobUuid)
                ->delete();

            if ($deleted) {
                $this->dispatch('show-notification', [
                    'message' => 'Trabajo fallido eliminado exitosamente',
                    'type' => 'success',
                ]);
                $this->refreshData();
            } else {
                $this->dispatch('show-notification', [
                    'message' => 'Trabajo fallido no encontrado',
                    'type' => 'error',
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('show-notification', [
                'message' => 'Error al eliminar el trabajo fallido: ' . $e->getMessage(),
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
     * Get uploads for admin monitoring (Redis-compatible).
     */
    public function getJobsProperty(): LengthAwarePaginator
    {
        $query = Upload::with('user')->select([
            'uploads.*',
            'users.name as user_name',
            'users.email as user_email'
        ])->leftJoin('users', 'uploads.user_id', '=', 'users.id');

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('uploads.status', $this->statusFilter);
        }

        // Apply user filter
        if ($this->userFilter) {
            $query->where('uploads.user_id', $this->userFilter);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $search = $this->search;
                $q->where('uploads.original_name', 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                  ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        // Apply date filters
        if ($this->dateFrom) {
            $query->where('uploads.created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('uploads.created_at', '<=', $this->dateTo . ' 23:59:59');
        }

        $query->orderBy('uploads.created_at', 'desc');

        return $query->paginate(20);
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
     * Get overall upload statistics (Redis-compatible).
     */
    public function getStatsProperty(): array
    {
        $totalJobs = Upload::count();
        $completedJobs = Upload::where('status', Upload::STATUS_COMPLETED)->count();
        $failedJobs = Upload::where('status', Upload::STATUS_FAILED)->count();

        // Calculate success rate
        $successRate = $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 0;

        // Calculate average processing time (in minutes) - SQLite compatible
        $processedUploads = Upload::whereNotNull('processed_at')
            ->select(['created_at', 'processed_at'])
            ->get();

        $avgProcessingMinutes = 0;
        if ($processedUploads->count() > 0) {
            $totalMinutes = $processedUploads->sum(function ($upload) {
                return $upload->created_at->diffInMinutes($upload->processed_at);
            });
            $avgProcessingMinutes = round($totalMinutes / $processedUploads->count(), 1);
        }

        return [
            'total_jobs' => $totalJobs,
            'queued_jobs' => Upload::where('status', Upload::STATUS_QUEUED)->count(),
            'processing_jobs' => Upload::where('status', Upload::STATUS_PROCESSING)->count(),
            'completed_jobs' => $completedJobs,
            'failed_jobs' => $failedJobs,
            'success_rate' => $successRate,
            'avg_processing_minutes' => $avgProcessingMinutes,
            'chart_data' => $this->getChartData(),
        ];
    }

    /**
     * Get user list for filtering (Redis-compatible).
     */
    public function getUsersProperty(): \Illuminate\Support\Collection
    {
        return DB::table('users')
            ->select(['id', 'name', 'email'])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('uploads')
                      ->whereColumn('uploads.user_id', 'users.id');
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
     * Get recent activity for dashboard (Redis-compatible).
     */
    public function getRecentActivityProperty(): \Illuminate\Support\Collection
    {
        // Since we're using Redis queues, we'll simulate recent activity from uploads
        return Upload::with('user')
            ->whereNotNull('processed_at')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($upload) {
                $level = $upload->status === Upload::STATUS_FAILED ? 'error' : 'info';
                $message = match($upload->status) {
                    Upload::STATUS_COMPLETED => "Upload completado: {$upload->original_name}",
                    Upload::STATUS_FAILED => "Upload fallido: {$upload->original_name}",
                    Upload::STATUS_PROCESSING => "Procesando: {$upload->original_name}",
                    default => "Upload: {$upload->original_name}"
                };

                return (object) [
                    'level' => $level,
                    'message' => $message,
                    'user_name' => $upload->user?->name ?? 'Usuario',
                    'created_at' => $upload->updated_at,
                ];
            });
    }

    /**
     * Get chart data for processing trends.
     */
    private function getChartData(): array
    {
        // Get data for the last 7 days
        $days = collect();
        $labels = collect();
        $completedData = collect();
        $failedData = collect();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayLabel = $date->format('M j');
            
            $completed = Upload::where('status', Upload::STATUS_COMPLETED)
                ->whereDate('processed_at', $date)
                ->count();
                
            $failed = Upload::where('status', Upload::STATUS_FAILED)
                ->whereDate('processed_at', $date)
                ->count();
            
            $labels->push($dayLabel);
            $completedData->push($completed);
            $failedData->push($failed);
        }
        
        return [
            'labels' => $labels->toArray(),
            'datasets' => [
                [
                    'label' => 'Completados',
                    'data' => $completedData->toArray(),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Fallidos',
                    'data' => $failedData->toArray(),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ]
            ]
        ];
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
        ])->layout('layouts.panel');
    }
}
