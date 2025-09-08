<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnhancedUploadCompleted extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    public Upload $upload;
    public UploadMetric $uploadMetric;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload, UploadMetric $uploadMetric)
    {
        $this->upload = $upload;
        $this->uploadMetric = $uploadMetric;

        $this->queue = config('emails.notifications.file_processing_completed.queue', 'emails');
        $this->delay = config('emails.notifications.file_processing_completed.delay', 0);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('emails.features.file_processing_emails', true) &&
            config('emails.notifications.file_processing_completed.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.file_processing_completed.template',
                          'emails.users.file-processing-completed');

        return (new MailMessage)
            ->subject('🎉 ¡Procesamiento Completado con Éxito!')
            ->view($template, [
                'user' => $notifiable,
                'upload' => $this->upload,
                'uploadMetric' => $this->uploadMetric,
                'durationText' => $this->formatDuration($this->uploadMetric->processing_duration_seconds),
                'unsubscribeToken' => $this->generateUnsubscribeToken($notifiable),
                'email' => $notifiable->email,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'upload_id' => $this->upload->id,
            'upload_metric_id' => $this->uploadMetric->id,
            'filename' => $this->upload->original_name,
            'status' => $this->upload->status,
            'line_count' => $this->uploadMetric->line_count,
            'processing_duration_seconds' => $this->uploadMetric->processing_duration_seconds,
            'credits_consumed' => $this->uploadMetric->credits_consumed,
            'file_size_bytes' => $this->uploadMetric->file_size_bytes,
            'processed_at' => $this->upload->processed_at,
        ];
    }

    /**
     * Format processing duration in a human-readable way.
     */
    private function formatDuration(?int $seconds): string
    {
        if (!$seconds) {
            return 'tiempo no disponible';
        }

        if ($seconds < 60) {
            return $seconds . ' segundos';
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            if ($remainingSeconds > 0) {
                return $minutes . ' minutos y ' . $remainingSeconds . ' segundos';
            }

            return $minutes . ' minutos';
        }

        $hours = floor($seconds / 3600);
        $remainingMinutes = floor(($seconds % 3600) / 60);

        if ($remainingMinutes > 0) {
            return $hours . ' horas y ' . $remainingMinutes . ' minutos';
        }

        return $hours . ' horas';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EnhancedUploadCompleted notification failed', [
            'upload_id' => $this->upload->id,
            'upload_metric_id' => $this->uploadMetric->id,
            'filename' => $this->upload->original_name,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Generate unsubscribe token for the user
     */
    protected function generateUnsubscribeToken(object $notifiable): string
    {
        // In a real implementation, this would generate a secure token
        // For now, return a placeholder
        return hash('sha256', $notifiable->email . config('app.key') . 'file_notifications');
    }
}
