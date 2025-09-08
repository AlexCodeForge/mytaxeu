<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UploadFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public Upload $upload;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
        $this->queue = config('emails.notifications.upload_failed.queue', 'emails');
        $this->delay = config('emails.notifications.upload_failed.delay', 0);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('emails.features.user_notifications', true) &&
            config('emails.notifications.upload_failed.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.upload_failed.template',
                          'emails.users.upload-failed');

        return (new MailMessage)
            ->subject('Error en Procesamiento de Archivo - MyTaxEU')
            ->view($template, [
                'upload' => $this->upload,
                'user' => $notifiable,
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
            'filename' => $this->upload->original_name,
            'status' => $this->upload->status,
            'failure_reason' => $this->upload->failure_reason,
            'failed_at' => now(),
        ];
    }
}
