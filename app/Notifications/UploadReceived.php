<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UploadReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public Upload $upload;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
        $this->queue = config('emails.notifications.upload_received.queue', 'emails');
        $this->delay = config('emails.notifications.upload_received.delay', 0);
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
            config('emails.notifications.upload_received.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.upload_received.template',
                          'emails.users.upload-received');

        return (new MailMessage)
            ->subject('Archivo Recibido Exitosamente - MyTaxEU')
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
            'size' => $this->upload->size_bytes,
            'status' => $this->upload->status,
            'received_at' => $this->upload->created_at,
        ];
    }
}
