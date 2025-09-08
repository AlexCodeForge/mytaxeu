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

class DailyJobStatusReport extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $jobData,
        public string $reportDate
    ) {
        $this->queue = config('emails.notifications.daily_job_status_report.queue', 'report-emails');
        $this->delay = config('emails.notifications.daily_job_status_report.delay', 0);
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
            config('emails.features.daily_reports', true) &&
            config('emails.notifications.daily_job_status_report.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.daily_job_status_report.template',
                          'emails.admin.daily-job-status-report');

        return (new MailMessage)
            ->subject("⚙️ Reporte Diario de Operaciones - {$this->reportDate}")
            ->view($template, [
                'jobData' => $this->jobData,
                'reportDate' => $this->reportDate,
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
            'type' => 'daily_job_status_report',
            'report_date' => $this->reportDate,
            'total_jobs' => $this->jobData['jobs']['total_jobs'] ?? 0,
            'completed_jobs' => $this->jobData['jobs']['completed_jobs'] ?? 0,
            'failed_jobs' => $this->jobData['jobs']['failed_jobs'] ?? 0,
            'success_rate' => $this->jobData['jobs']['success_rate'] ?? 0,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DailyJobStatusReport notification failed', [
            'report_date' => $this->reportDate,
            'job_data_summary' => [
                'total_jobs' => $this->jobData['jobs']['total_jobs'] ?? 0,
                'success_rate' => $this->jobData['jobs']['success_rate'] ?? 0,
            ],
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}


