<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\JobLogCreated;
use App\Events\JobStatusUpdated;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobStatusService
{
    /**
     * Valid job statuses.
     */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const VALID_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * Valid log levels.
     */
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';

    public const VALID_LOG_LEVELS = [
        self::LOG_LEVEL_INFO,
        self::LOG_LEVEL_WARNING,
        self::LOG_LEVEL_ERROR,
    ];

    /**
     * Update job status with validation and events.
     */
    public function updateJobStatus(
        int $jobId,
        string $status,
        ?int $userId = null,
        ?string $fileName = null,
        ?string $errorMessage = null
    ): bool {
        // Validate status
        if (!in_array($status, self::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        try {
            DB::beginTransaction();

            // Get current job data
            $currentJob = DB::table('jobs')->where('id', $jobId)->first();
            if (!$currentJob) {
                throw new \Exception("Job not found: {$jobId}");
            }

            // Prepare update data
            $updateData = ['status' => $status];

            // Set timestamps based on status
            switch ($status) {
                case self::STATUS_PROCESSING:
                    if (!$currentJob->started_at) {
                        $updateData['started_at'] = now();
                    }
                    break;
                case self::STATUS_COMPLETED:
                case self::STATUS_FAILED:
                    if (!$currentJob->completed_at) {
                        $updateData['completed_at'] = now();
                    }
                    break;
            }

            // Add optional fields
            if ($userId !== null) {
                $updateData['user_id'] = $userId;
            }
            if ($fileName !== null) {
                $updateData['file_name'] = $fileName;
            }
            if ($errorMessage !== null) {
                $updateData['error_message'] = $errorMessage;
            }

            // Update job status
            $updated = DB::table('jobs')
                ->where('id', $jobId)
                ->update($updateData);

            if (!$updated) {
                throw new \Exception("Failed to update job status");
            }

            // Log the status change
            $this->logJobActivity(
                $jobId,
                self::LOG_LEVEL_INFO,
                "Job status changed to: {$status}",
                [
                    'previous_status' => $currentJob->status,
                    'new_status' => $status,
                    'user_id' => $userId,
                    'file_name' => $fileName,
                ]
            );

            DB::commit();

            // Fire event for real-time updates
            event(new JobStatusUpdated($jobId, $status, $userId, $fileName));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update job status', [
                'job_id' => $jobId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Log job activity with proper categorization.
     */
    public function logJobActivity(
        int $jobId,
        string $level,
        string $message,
        array $metadata = []
    ): bool {
        // Validate log level
        if (!in_array($level, self::VALID_LOG_LEVELS)) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }

        try {
            $logId = DB::table('job_logs')->insertGetId([
                'job_id' => $jobId,
                'level' => $level,
                'message' => $message,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'created_at' => now(),
            ]);

            // Fire event for log creation
            event(new JobLogCreated($logId, $jobId, $level, $message, $metadata));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to log job activity', [
                'job_id' => $jobId,
                'level' => $level,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get jobs for a specific user with filtering and pagination.
     */
    public function getJobsForUser(
        int $userId,
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = DB::table('jobs')
            ->where('user_id', $userId)
            ->select([
                'id',
                'status',
                'file_name',
                'error_message',
                'created_at',
                'started_at',
                'completed_at',
            ]);

        // Apply status filter
        if ($status && in_array($status, self::VALID_STATUSES)) {
            $query->where('status', $status);
        }

        // Apply search filter
        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('file_name', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all jobs for admin monitoring with comprehensive filtering.
     */
    public function getJobsForAdmin(
        ?string $status = null,
        ?int $userId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = DB::table('jobs')
            ->leftJoin('users', 'jobs.user_id', '=', 'users.id')
            ->select([
                'jobs.id',
                'jobs.status',
                'jobs.file_name',
                'jobs.error_message',
                'jobs.created_at',
                'jobs.started_at',
                'jobs.completed_at',
                'jobs.user_id',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        // Apply status filter
        if ($status && in_array($status, self::VALID_STATUSES)) {
            $query->where('jobs.status', $status);
        }

        // Apply user filter
        if ($userId) {
            $query->where('jobs.user_id', $userId);
        }

        // Apply search filter
        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('jobs.file_name', 'LIKE', "%{$search}%")
                  ->orWhere('jobs.id', 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                  ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        // Apply date range filter
        if ($dateFrom) {
            $query->where('jobs.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('jobs.created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Order by most recent first
        $query->orderBy('jobs.created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Retry a failed job with security validation.
     */
    public function retryFailedJob(string $failedJobUuid, int $requestingUserId, bool $isAdmin = false): bool
    {
        try {
            DB::beginTransaction();

            // Get failed job
            $failedJob = DB::table('failed_jobs')
                ->where('uuid', $failedJobUuid)
                ->first();

            if (!$failedJob) {
                throw new \Exception("Failed job not found: {$failedJobUuid}");
            }

            // Security check: Users can only retry their own jobs unless admin
            if (!$isAdmin && $failedJob->user_id !== $requestingUserId) {
                throw new \Exception("Unauthorized: Cannot retry job for another user");
            }

            // Decode payload and create new job
            $payload = json_decode($failedJob->payload, true);
            if (!$payload) {
                throw new \Exception("Invalid job payload");
            }

            // Insert new job with retry status
            $newJobId = DB::table('jobs')->insertGetId([
                'queue' => $failedJob->queue,
                'payload' => $failedJob->payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time(),
                'status' => self::STATUS_QUEUED,
                'user_id' => $failedJob->user_id,
                'file_name' => $failedJob->file_name,
            ]);

            // Update retry count in failed job
            DB::table('failed_jobs')
                ->where('uuid', $failedJobUuid)
                ->increment('retry_count');

            // Log the retry action
            $this->logJobActivity(
                $newJobId,
                self::LOG_LEVEL_INFO,
                'Job retried from failed state',
                [
                    'original_failed_job_uuid' => $failedJobUuid,
                    'retried_by_user_id' => $requestingUserId,
                    'is_admin_retry' => $isAdmin,
                ]
            );

            DB::commit();

            // Fire event for the new job
            event(new JobStatusUpdated($newJobId, self::STATUS_QUEUED, $failedJob->user_id, $failedJob->file_name));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to retry job', [
                'failed_job_uuid' => $failedJobUuid,
                'requesting_user_id' => $requestingUserId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get job logs for a specific job with pagination.
     */
    public function getJobLogs(int $jobId, int $perPage = 10): LengthAwarePaginator
    {
        return DB::table('job_logs')
            ->where('job_id', $jobId)
            ->select(['id', 'level', 'message', 'metadata', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get job statistics for admin dashboard.
     */
    public function getJobStatistics(?int $userId = null): array
    {
        $query = DB::table('jobs');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $stats = $query
            ->select([
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "processing" THEN 1 END) as processing_jobs'),
                DB::raw('COUNT(CASE WHEN status = "queued" THEN 1 END) as queued_jobs'),
                DB::raw('AVG(CASE WHEN completed_at IS NOT NULL AND started_at IS NOT NULL
                         THEN (julianday(completed_at) - julianday(started_at)) * 24 * 60
                         END) as avg_processing_minutes'),
            ])
            ->first();

        return [
            'total_jobs' => (int) $stats->total_jobs,
            'completed_jobs' => (int) $stats->completed_jobs,
            'failed_jobs' => (int) $stats->failed_jobs,
            'processing_jobs' => (int) $stats->processing_jobs,
            'queued_jobs' => (int) $stats->queued_jobs,
            'success_rate' => $stats->total_jobs > 0
                ? round(($stats->completed_jobs / $stats->total_jobs) * 100, 2)
                : 0,
            'avg_processing_minutes' => $stats->avg_processing_minutes
                ? round($stats->avg_processing_minutes, 2)
                : 0,
        ];
    }

    /**
     * Initialize job metadata when creating a new job.
     */
    public function initializeJobMetadata(
        int $jobId,
        int $userId,
        string $fileName,
        string $status = self::STATUS_QUEUED
    ): bool {
        try {
            $updated = DB::table('jobs')
                ->where('id', $jobId)
                ->update([
                    'status' => $status,
                    'user_id' => $userId,
                    'file_name' => $fileName,
                ]);

            if ($updated) {
                $this->logJobActivity(
                    $jobId,
                    self::LOG_LEVEL_INFO,
                    'Job initialized with metadata',
                    [
                        'user_id' => $userId,
                        'file_name' => $fileName,
                        'initial_status' => $status,
                    ]
                );
            }

            return (bool) $updated;

        } catch (\Exception $e) {
            Log::error('Failed to initialize job metadata', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

}
