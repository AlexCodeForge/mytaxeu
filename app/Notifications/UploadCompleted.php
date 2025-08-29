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
        return (new MailMessage)
            ->subject('Procesamiento de CSV Completado - MyTaxEU')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Su archivo CSV ha sido procesado exitosamente.')
            ->line('**Detalles del archivo:**')
            ->line('• Nombre: ' . $this->upload->original_name)
            ->line('• Filas procesadas: ' . number_format($this->upload->rows_count ?? 0))
            ->line('• Tamaño: ' . $this->upload->formatted_size)
            ->line('• Procesado el: ' . $this->upload->processed_at?->format('d/m/Y H:i'))
            ->action('Ver mis archivos', route('dashboard'))
            ->line('Gracias por usar MyTaxEU.');
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
