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

class FileUploadConfirmation extends Notification implements ShouldQueue
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
        $this->queue = config('emails.notifications.file_upload_confirmation.queue', 'emails');
        $this->delay = config('emails.notifications.file_upload_confirmation.delay', 0);
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
            config('emails.notifications.file_upload_confirmation.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.file_upload_confirmation.template',
                          'emails.users.file-upload-confirmation');

        return (new MailMessage)
            ->subject('📁 Archivo Subido Correctamente - En Cola de Procesamiento')
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
            'type' => 'file_upload_confirmation',
            'upload_id' => $this->uploadData['id'] ?? null,
            'file_name' => $this->fileData['name'] ?? '',
            'file_size' => $this->fileData['size'] ?? 0,
            'file_rows' => $this->fileData['rows'] ?? 0,
            'estimated_processing_time' => $this->processingData['estimated_time'] ?? null,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FileUploadConfirmation notification failed', [
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


