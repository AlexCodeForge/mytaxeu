<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UploadQueued extends Notification implements ShouldQueue
{
    use Queueable;

    public Upload $upload;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
        $this->queue = config('emails.notifications.upload_queued.queue', 'emails');
        $this->delay = config('emails.notifications.upload_queued.delay', 0);
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
            config('emails.notifications.upload_queued.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.upload_queued.template',
                          'emails.users.upload-queued');

        return (new MailMessage)
            ->subject('Archivo en Cola de Procesamiento - MyTaxEU')
            ->view($template, [
                'upload' => $this->upload,
                'user' => $notifiable,
                'queuePosition' => $this->estimateQueuePosition(),
                'estimatedWaitTime' => $this->estimateWaitTime(),
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
            'queue_position' => $this->estimateQueuePosition(),
        ];
    }

    /**
     * Estimate queue position for this upload.
     */
    private function estimateQueuePosition(): int
    {
        try {
            if (!$this->upload->created_at) {
                return 1;
            }

            return Upload::where('status', 'queued')
                ->where('created_at', '<', $this->upload->created_at)
                ->count() + 1;
        } catch (\Exception $e) {
            // If there's any database error, just return a reasonable default
            return 3;
        }
    }

    /**
     * Estimate wait time based on queue position.
     */
    private function estimateWaitTime(): string
    {
        $position = $this->estimateQueuePosition();

        if ($position <= 3) {
            return '2-5 minutos';
        } elseif ($position <= 10) {
            return '5-15 minutos';
        } else {
            return '15-30 minutos';
        }
    }
}
