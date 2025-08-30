<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\JobStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class JobStatusUpdateNotificationListener implements ShouldQueue
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
    public function handle(JobStatusUpdated $event): void
    {
        // Log the status update for monitoring
        Log::info('Job status updated notification processed', [
            'job_id' => $event->jobId,
            'status' => $event->status,
            'user_id' => $event->userId,
            'file_name' => $event->fileName,
            'timestamp' => $event->timestamp,
        ]);

        // Here you could add additional notification logic such as:
        // - Sending push notifications
        // - Triggering email notifications (handled in separate task)
        // - Updating cache or external services
        // - Integration with third-party monitoring services

        // Example: Update user notification preferences or counters
        if ($event->userId && in_array($event->status, ['completed', 'failed'])) {
            $this->updateUserNotificationCounters($event);
        }
    }

    /**
     * Update user notification counters for completed/failed jobs.
     */
    private function updateUserNotificationCounters(JobStatusUpdated $event): void
    {
        try {
            // This could update user notification badges, counters, etc.
            // For now, we'll just log it as an example
            Log::info('User notification counter updated', [
                'user_id' => $event->userId,
                'job_status' => $event->status,
                'job_id' => $event->jobId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user notification counters', [
                'user_id' => $event->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(JobStatusUpdated $event, \Throwable $exception): void
    {
        Log::error('JobStatusUpdateNotificationListener failed', [
            'job_id' => $event->jobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
