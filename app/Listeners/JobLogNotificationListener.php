<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\JobLogCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class JobLogNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobLogCreated $event): void
    {
        // Handle different log levels with appropriate actions
        switch ($event->level) {
            case 'error':
                $this->handleErrorLog($event);
                break;
            case 'warning':
                $this->handleWarningLog($event);
                break;
            case 'info':
                $this->handleInfoLog($event);
                break;
        }

        // Log the event for monitoring purposes
        Log::info('Job log notification processed', [
            'log_id' => $event->logId,
            'job_id' => $event->jobId,
            'level' => $event->level,
            'message' => $event->message,
            'timestamp' => $event->timestamp,
        ]);
    }

    /**
     * Handle error level logs - these might need immediate attention.
     */
    private function handleErrorLog(JobLogCreated $event): void
    {
        // For error logs, we might want to:
        // - Send immediate notifications to admins
        // - Trigger alerting systems
        // - Update monitoring dashboards

        Log::error('Job error logged', [
            'job_id' => $event->jobId,
            'message' => $event->message,
            'metadata' => $event->metadata,
        ]);

        // Example: Could trigger admin alerts for critical errors
        $this->triggerAdminAlert($event);
    }

    /**
     * Handle warning level logs.
     */
    private function handleWarningLog(JobLogCreated $event): void
    {
        Log::warning('Job warning logged', [
            'job_id' => $event->jobId,
            'message' => $event->message,
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * Handle info level logs.
     */
    private function handleInfoLog(JobLogCreated $event): void
    {
        // Info logs are typically just for tracking progress
        // They might update progress indicators or general monitoring
    }

    /**
     * Trigger admin alert for critical errors.
     */
    private function triggerAdminAlert(JobLogCreated $event): void
    {
        try {
            // This could integrate with:
            // - Slack notifications
            // - Email alerts
            // - SMS alerts
            // - External monitoring services like PagerDuty

            Log::info('Admin alert triggered for job error', [
                'job_id' => $event->jobId,
                'log_id' => $event->logId,
                'message' => $event->message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger admin alert', [
                'job_id' => $event->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(JobLogCreated $event, \Throwable $exception): void
    {
        Log::error('JobLogNotificationListener failed', [
            'log_id' => $event->logId,
            'job_id' => $event->jobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
