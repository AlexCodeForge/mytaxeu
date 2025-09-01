<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Notifications\EnhancedUploadCompleted;
use App\Notifications\EnhancedUploadFailed;
use App\Notifications\EnhancedUploadProcessingStarted;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send success notification for completed upload.
     */
    public function sendSuccessNotification(Upload $upload, ?UploadMetric $uploadMetric = null): bool
    {
        // Check if notification was already sent
        if ($upload->notification_sent_at && $upload->notification_type === 'success') {
            Log::info('Success notification already sent', [
                'upload_id' => $upload->id,
                'notification_sent_at' => $upload->notification_sent_at,
            ]);
            return false;
        }

        try {
            // Create default upload metric if none provided
            if (!$uploadMetric) {
                $uploadMetric = $this->createDefaultUploadMetric($upload);
            }

            // Send the notification
            $upload->user->notify(new EnhancedUploadCompleted($upload, $uploadMetric));

            // Track notification sending
            $upload->update([
                'notification_sent_at' => now(),
                'notification_type' => 'success',
            ]);

            Log::info('Success notification sent successfully', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'processing_duration' => $uploadMetric->processing_duration_seconds,
                'line_count' => $uploadMetric->line_count,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send success notification', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send failure notification for failed upload.
     */
    public function sendFailureNotification(Upload $upload, ?UploadMetric $uploadMetric = null): bool
    {
        // Check if notification was already sent
        if ($upload->notification_sent_at && $upload->notification_type === 'failure') {
            Log::info('Failure notification already sent', [
                'upload_id' => $upload->id,
                'notification_sent_at' => $upload->notification_sent_at,
            ]);
            return false;
        }

        try {
            // Create default upload metric if none provided
            if (!$uploadMetric) {
                $uploadMetric = $this->createDefaultUploadMetric($upload);
            }

            // Send the notification
            $upload->user->notify(new EnhancedUploadFailed($upload, $uploadMetric));

            // Track notification sending
            $upload->update([
                'notification_sent_at' => now(),
                'notification_type' => 'failure',
            ]);

            Log::info('Failure notification sent successfully', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error_message' => $upload->failure_reason,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send failure notification', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send processing started notification.
     */
    public function sendProcessingStartedNotification(Upload $upload, ?UploadMetric $uploadMetric = null): bool
    {
        // Check if notification was already sent
        if ($upload->notification_sent_at && $upload->notification_type === 'processing') {
            Log::info('Processing notification already sent', [
                'upload_id' => $upload->id,
                'notification_sent_at' => $upload->notification_sent_at,
            ]);
            return false;
        }

        try {
            // Create default upload metric if none provided
            if (!$uploadMetric) {
                $uploadMetric = $this->createDefaultUploadMetric($upload);
            }

            // Send the notification (if we decide to implement processing notifications)
            // For now, just track it without sending email to avoid spam

            // Track notification sending
            $upload->update([
                'notification_sent_at' => now(),
                'notification_type' => 'processing',
            ]);

            Log::info('Processing notification tracked', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to track processing notification', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a default upload metric for uploads that don't have one yet.
     */
    private function createDefaultUploadMetric(Upload $upload): UploadMetric
    {
        return new UploadMetric([
            'user_id' => $upload->user_id,
            'upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'file_size_bytes' => $upload->size_bytes,
            'line_count' => $upload->csv_line_count ?? $upload->rows_count ?? 0,
            'processing_started_at' => $upload->created_at,
            'processing_completed_at' => $upload->processed_at,
            'processing_duration_seconds' => $upload->processed_at
                ? $upload->created_at->diffInSeconds($upload->processed_at)
                : null,
            'credits_consumed' => $upload->status === Upload::STATUS_COMPLETED ? 1 : 0,
            'status' => match($upload->status) {
                Upload::STATUS_COMPLETED => UploadMetric::STATUS_COMPLETED,
                Upload::STATUS_FAILED => UploadMetric::STATUS_FAILED,
                Upload::STATUS_PROCESSING => UploadMetric::STATUS_PROCESSING,
                default => UploadMetric::STATUS_PENDING,
            },
            'error_message' => $upload->failure_reason,
        ]);
    }
}
