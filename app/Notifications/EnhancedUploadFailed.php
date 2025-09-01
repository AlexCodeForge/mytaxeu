<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnhancedUploadFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public Upload $upload;
    public UploadMetric $uploadMetric;

    /**
     * Create a new notification instance.
     */
    public function __construct(Upload $upload, UploadMetric $uploadMetric)
    {
        $this->upload = $upload;
        $this->uploadMetric = $uploadMetric;
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
        $durationText = $this->formatDuration($this->uploadMetric->processing_duration_seconds);
        $errorMessage = $this->uploadMetric->error_message ?? $this->upload->failure_reason ?? 'Error desconocido';

        $summary = "Desafortunadamente, el procesamiento de su archivo **{$this->upload->original_name}** " .
                  "ha fallado después de **{$durationText}** de procesamiento. " .
                  "**Error:** {$errorMessage}";

        return (new MailMessage)
            ->subject('❌ Error en Procesamiento - MyTaxEU')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line($summary)
            ->line('')
            ->line('**🔍 Detalles del Error:**')
            ->line('• **Archivo:** ' . $this->upload->original_name)
            ->line('• **Error:** ' . $errorMessage)
            ->line('• **Tiempo antes del fallo:** ' . $durationText)
            ->line('• **Líneas procesadas antes del fallo:** ' . number_format($this->uploadMetric->line_count))
            ->line('• **Falló el:** ' . ($this->uploadMetric->processing_completed_at ?? $this->upload->processed_at)?->format('d/m/Y H:i'))
            ->line('')
            ->line('**🛠️ Posibles Soluciones:**')
            ->line('• Verifique que su archivo CSV tenga el formato correcto')
            ->line('• Asegúrese de que contiene la columna ACTIVITY_PERIOD requerida')
            ->line('• Revise que no exceda el límite de 3 períodos distintos')
            ->line('• Compruebe que el archivo no esté corrupto')
            ->line('')
            ->action('📂 Ver Dashboard', route('dashboard'))
            ->line('Si el problema persiste, puede contactar con nuestro soporte técnico con el ID del archivo: **' . $this->upload->id . '**')
            ->line('Lamentamos las molestias y gracias por usar MyTaxEU.');
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
            'error_message' => $this->uploadMetric->error_message ?? $this->upload->failure_reason,
            'line_count' => $this->uploadMetric->line_count,
            'processing_duration_seconds' => $this->uploadMetric->processing_duration_seconds,
            'file_size_bytes' => $this->uploadMetric->file_size_bytes,
            'failed_at' => $this->upload->processed_at,
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
}
