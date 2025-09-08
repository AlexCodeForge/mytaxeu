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

class FailedJobAlert extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $jobDetails,
        public array $errorDetails,
        public array $systemContext
    ) {
        // Use high priority queue for alerts
        $this->queue = config('emails.notifications.failed_job_alert.queue', 'priority-emails');
        $this->delay = config('emails.notifications.failed_job_alert.delay', 0);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('emails.features.admin_notifications', true) &&
            config('emails.features.operational_alerts', true) &&
            config('emails.notifications.failed_job_alert.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.failed_job_alert.template',
                          'emails.admin.failed-job-alert');

        $severity = $this->determineSeverity();
        $subject = $this->generateSubject($severity);

        return (new MailMessage)
            ->subject($subject)
            ->view($template, [
                'jobDetails' => $this->jobDetails,
                'errorDetails' => $this->errorDetails,
                'systemContext' => $this->systemContext,
                'severity' => $severity,
                'email' => $notifiable->email ?? 'admin@mytaxeu.com',
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
            'type' => 'failed_job_alert',
            'job_id' => $this->jobDetails['job_id'] ?? null,
            'upload_id' => $this->jobDetails['upload_id'] ?? null,
            'user_id' => $this->jobDetails['user_id'] ?? null,
            'error_type' => $this->errorDetails['type'] ?? 'unknown',
            'error_message' => $this->errorDetails['message'] ?? '',
            'severity' => $this->determineSeverity(),
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FailedJobAlert notification failed', [
            'job_details' => $this->jobDetails,
            'error_details' => $this->errorDetails,
            'system_context' => $this->systemContext,
            'notification_error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Determine the severity level of the failed job.
     */
    protected function determineSeverity(): string
    {
        $errorType = $this->errorDetails['type'] ?? '';
        $retryCount = $this->jobDetails['retry_count'] ?? 0;
        $recentFailures = $this->systemContext['recent_failures'] ?? 0;

        // High severity conditions
        if ($recentFailures >= 10) {
            return 'critical';
        }

        if (in_array($errorType, ['database_error', 'storage_error', 'system_error'])) {
            return 'high';
        }

        if ($retryCount >= 3) {
            return 'high';
        }

        // Medium severity conditions
        if ($recentFailures >= 5) {
            return 'medium';
        }

        if (in_array($errorType, ['validation_error', 'file_format_error'])) {
            return 'medium';
        }

        // Default to low severity
        return 'low';
    }

    /**
     * Generate appropriate subject line based on severity.
     */
    protected function generateSubject(string $severity): string
    {
        $urgencyMap = [
            'critical' => '🚨 CRÍTICO',
            'high' => '🔴 ALTA',
            'medium' => '🟡 MEDIA',
            'low' => '🟢 BAJA',
        ];

        $urgency = $urgencyMap[$severity] ?? '🟢 BAJA';
        $jobType = $this->jobDetails['type'] ?? 'Trabajo';

        return "⚠️ {$urgency}: Fallo en {$jobType} - MyTaxEU";
    }
}


