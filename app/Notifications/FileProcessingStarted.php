<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FileProcessingStarted extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $uploadData,
        public array $fileData,
        public array $processingData
    ) {
        $this->queue = config('emails.notifications.file_processing_started.queue', 'emails');
        $this->delay = config('emails.notifications.file_processing_started.delay', 0);
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
            config('emails.notifications.file_processing_started.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.file_processing_started.template',
                          'emails.users.file-processing-started');

        return (new MailMessage)
            ->subject('🔄 Procesamiento Iniciado - Tu Archivo está siendo Transformado')
            ->view($template, [
                'user' => $notifiable,
                'upload' => $this->uploadData,
                'file' => $this->fileData,
                'processing' => $this->processingData,
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
            'type' => 'file_processing_started',
            'upload_id' => $this->uploadData['id'] ?? null,
            'file_name' => $this->fileData['name'] ?? '',
            'processing_started_at' => $this->processingData['started_at'] ?? now(),
            'estimated_completion' => $this->processingData['estimated_completion'] ?? null,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FileProcessingStarted notification failed', [
            'upload_data' => $this->uploadData,
            'file_data' => $this->fileData,
            'processing_data' => $this->processingData,
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


