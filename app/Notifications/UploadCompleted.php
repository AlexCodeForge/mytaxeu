<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UploadCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public Upload $upload;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.upload_completed.template',
                          'emails.users.upload-completed');

        return (new MailMessage)
            ->subject('Procesamiento Completado Exitosamente - MyTaxEU')
            ->view($template, [
                'upload' => $this->upload,
                'user' => $notifiable,
                'qualityScore' => $this->calculateQualityScore(),
                'processingTime' => $this->getProcessingTime(),
                'totalProcessedFiles' => $this->getTotalProcessedFiles($notifiable),
            ]);
    }

    /**
     * Calculate a quality score for the processed data.
     */
    private function calculateQualityScore(): int
    {
        $score = 85; // Base score

        // Adjust based on file characteristics
        if ($this->upload->rows_count && $this->upload->rows_count > 1000) {
            $score += 5; // Bonus for larger files
        }

        if ($this->upload->failure_reason === null) {
            $score += 10; // Bonus for no errors
        }

        return min(100, $score);
    }

    /**
     * Get human readable processing time.
     */
    private function getProcessingTime(): string
    {
        if ($this->upload->processed_at && $this->upload->created_at) {
            return $this->upload->created_at->diffForHumans($this->upload->processed_at, true);
        }

        return '2-3 minutos';
    }

    /**
     * Get total processed files for this user.
     */
    private function getTotalProcessedFiles($notifiable): int
    {
        if (method_exists($notifiable, 'uploads')) {
            return $notifiable->uploads()->where('status', 'completed')->count();
        }

        return 1;
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
            'processed_at' => $this->upload->processed_at,
        ];
    }
}
