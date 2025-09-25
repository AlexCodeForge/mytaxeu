<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Notifications\EnhancedUploadCompleted;
use App\Notifications\EnhancedUploadFailed;
use App\Notifications\EnhancedUploadProcessingStarted;
use App\Notifications\FileUploadConfirmation;
use App\Notifications\FileProcessingStarted;
use App\Services\CreditService;
use App\Services\EmailConfigService;
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
            // Check if feature is enabled
            if (!EmailConfigService::isFeatureEnabled('file_processing_emails')) {
                Log::debug('File processing emails are disabled, skipping success notification', [
                    'upload_id' => $upload->id,
                ]);
                return false;
            }

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
            // Check if feature is enabled
            if (!EmailConfigService::isFeatureEnabled('file_processing_emails')) {
                Log::debug('File processing emails are disabled, skipping failure notification', [
                    'upload_id' => $upload->id,
                ]);
                return false;
            }

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
     * Send upload confirmation notification immediately after file upload.
     */
    public function sendUploadConfirmationNotification(Upload $upload): bool
    {
        try {
            // Check if feature is enabled
            if (!EmailConfigService::isFeatureEnabled('file_processing_emails')) {
                Log::debug('File processing emails are disabled, skipping upload confirmation', [
                    'upload_id' => $upload->id,
                ]);
                return false;
            }

            // Prepare upload data
            $uploadData = [
                'id' => $upload->id,
                'created_at' => $upload->created_at,
                'status' => $upload->status,
            ];

            // Prepare file data
            $fileData = [
                'name' => $upload->original_name,
                'size' => $upload->size_bytes,
                'size_formatted' => $this->formatFileSize($upload->size_bytes),
                'rows' => $upload->csv_line_count ?? $upload->rows_count ?? 0,
            ];

            // Prepare processing data
            $processingData = [
                'estimated_time' => $this->estimateProcessingTime($fileData['rows']),
                'queue_position' => $this->getQueuePosition(),
                'credits_used' => $this->estimateCreditsUsed($fileData['rows']),
                'credits_remaining' => $this->getUserRemainingCredits($upload->user),
                'tips' => $this->generateProcessingTips($fileData),
            ];

            // Send the notification
            $upload->user->notify(new FileUploadConfirmation($uploadData, $fileData, $processingData));

            Log::info('Upload confirmation notification sent', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'file_name' => $upload->original_name,
                'file_rows' => $fileData['rows'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send upload confirmation notification', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send processing started notification when job begins.
     */
    public function sendProcessingStartedNotification(Upload $upload, ?UploadMetric $uploadMetric = null): bool
    {
        try {
            // Check if feature is enabled
            if (!EmailConfigService::isFeatureEnabled('file_processing_emails')) {
                Log::debug('File processing emails are disabled, skipping processing started', [
                    'upload_id' => $upload->id,
                ]);
                return false;
            }

            // Create default upload metric if none provided
            if (!$uploadMetric) {
                $uploadMetric = $this->createDefaultUploadMetric($upload);
            }

            // Prepare upload data
            $uploadData = [
                'id' => $upload->id,
                'created_at' => $upload->created_at,
                'status' => $upload->status,
            ];

            // Prepare file data
            $fileData = [
                'name' => $upload->original_name,
                'size' => $upload->size_bytes,
                'size_formatted' => $this->formatFileSize($upload->size_bytes),
                'rows' => $uploadMetric->line_count,
            ];

            // Prepare processing data
            $processingData = [
                'started_at' => now(),
                'estimated_completion' => now()->addMinutes($this->estimateProcessingMinutes($fileData['rows'])),
                'estimated_duration' => $this->estimateProcessingTime($fileData['rows']),
                'progress_percentage' => 15, // Initial progress
                'file_type' => 'Amazon CSV',
                'notifications_enabled' => true,
                'processing_tips' => $this->generateProcessingTips($fileData),
            ];

            // Send the notification
            $upload->user->notify(new FileProcessingStarted($uploadData, $fileData, $processingData));

            Log::info('Processing started notification sent', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'file_name' => $upload->original_name,
                'estimated_duration' => $processingData['estimated_duration'],
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send processing started notification', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes) {
            return 'N/A';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        } else {
            return round($bytes / 1073741824, 1) . ' GB';
        }
    }

    /**
     * Estimate processing time based on file rows.
     */
    private function estimateProcessingTime(int $rows): string
    {
        if ($rows <= 100) {
            return '1-2 minutos';
        } elseif ($rows <= 500) {
            return '2-3 minutos';
        } elseif ($rows <= 1000) {
            return '3-5 minutos';
        } elseif ($rows <= 5000) {
            return '5-10 minutos';
        } else {
            return '10-15 minutos';
        }
    }

    /**
     * Estimate processing time in minutes for calculations.
     */
    private function estimateProcessingMinutes(int $rows): int
    {
        if ($rows <= 100) {
            return 2;
        } elseif ($rows <= 500) {
            return 3;
        } elseif ($rows <= 1000) {
            return 5;
        } elseif ($rows <= 5000) {
            return 8;
        } else {
            return 12;
        }
    }

    /**
     * Get current queue position (simplified implementation).
     */
    private function getQueuePosition(): string
    {
        // In a real implementation, this would check the actual queue
        return 'En proceso';
    }

    /**
     * Estimate credits to be used based on file rows.
     */
    private function estimateCreditsUsed(int $rows): int
    {
        // Basic estimation: 1 credit per 10 rows, minimum 1 credit
        return (int) max(1, ceil($rows / 10));
    }

    /**
     * Get user's remaining credits.
     */
    private function getUserRemainingCredits($user): int
    {
        try {
            $creditService = app(CreditService::class);
            return $creditService->getCreditBalance($user);
        } catch (\Exception $e) {
            Log::warning('Could not fetch user remaining credits', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Generate processing tips based on file characteristics.
     */
    private function generateProcessingTips(array $fileData): array
    {
        $tips = [];

        if ($fileData['rows'] > 1000) {
            $tips[] = 'Archivo de gran volumen: optimización automática de rendimiento aplicada';
        }

        if ($fileData['size'] > 1048576) { // > 1MB
            $tips[] = 'Archivo grande detectado: procesamiento en lotes para mayor eficiencia';
        }

        $tips[] = 'Validación de IVA europea incluida automáticamente';
        $tips[] = 'Generación de informes para modelos 349 y 369 activada';

        if ($fileData['rows'] <= 100) {
            $tips[] = 'Archivo pequeño: procesamiento ultrarrápido disponible';
        }

        return $tips;
    }

    /**
     * Enhanced error handling for notification failures.
     */
    public function handleNotificationFailure(Upload $upload, string $notificationType, \Exception $exception): void
    {
        Log::error('Notification delivery failed', [
            'upload_id' => $upload->id,
            'user_id' => $upload->user_id,
            'notification_type' => $notificationType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'retry_count' => $upload->notification_retry_count ?? 0,
        ]);

        // Update retry count
        $upload->increment('notification_retry_count');

        // If we've retried too many times, mark as failed
        if (($upload->notification_retry_count ?? 0) >= 3) {
            $upload->update(['notification_failed_at' => now()]);

            Log::warning('Notification permanently failed after retries', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'notification_type' => $notificationType,
            ]);
        }
    }

    /**
     * Track email delivery success.
     */
    public function trackEmailDelivery(Upload $upload, string $notificationType): void
    {
        Log::info('Email delivery tracked', [
            'upload_id' => $upload->id,
            'user_id' => $upload->user_id,
            'notification_type' => $notificationType,
            'delivered_at' => now(),
        ]);

        // Update delivery tracking
        $upload->update([
            'notification_delivered_at' => now(),
            'notification_type' => $notificationType,
        ]);
    }
}
