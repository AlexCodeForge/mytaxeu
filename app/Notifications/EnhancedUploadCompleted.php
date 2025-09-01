<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnhancedUploadCompleted extends Notification implements ShouldQueue
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
        $fileSizeText = $this->uploadMetric->getFormattedSizeAttribute();

        $summary = "Su archivo **{$this->upload->original_name}** ha sido procesado exitosamente. " .
                  "**{$this->uploadMetric->line_count} líneas** procesadas en **{$durationText}**, " .
                  "consumiendo **{$this->uploadMetric->credits_consumed} créditos**. " .
                  "Tamaño del archivo: **{$fileSizeText}**.";

        $mailMessage = (new MailMessage)
            ->subject('✅ Procesamiento Completado - MyTaxEU')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line($summary)
            ->line('')
            ->line('**📊 Resumen del Procesamiento:**')
            ->line('• **Archivo:** ' . $this->upload->original_name)
            ->line('• **Líneas procesadas:** ' . number_format($this->uploadMetric->line_count))
            ->line('• **Tiempo de procesamiento:** ' . $durationText)
            ->line('• **Créditos consumidos:** ' . $this->uploadMetric->credits_consumed)
            ->line('• **Tamaño del archivo:** ' . $fileSizeText)
            ->line('• **Completado el:** ' . ($this->uploadMetric->processing_completed_at ?? $this->upload->processed_at)?->format('d/m/Y H:i'));

        // Add download link if transformed file exists
        if ($this->upload->hasTransformedFile()) {
            $downloadUrl = route('download.upload', ['upload' => $this->upload->id]);
            $mailMessage->action('📥 Descargar Archivo Procesado', $downloadUrl);
        } else {
            $mailMessage->action('📂 Ver en Dashboard', route('dashboard'));
        }

        return $mailMessage
            ->line('')
            ->line('Puede revisar todos sus archivos procesados en su **dashboard** o contactarnos si tiene alguna pregunta.')
            ->line('Acceda a su historial completo de cargas y descargas desde su panel de control.')
            ->line('Gracias por usar MyTaxEU para sus necesidades fiscales.');
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
}
