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

class MonthlySalesReport extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $reportData,
        public string $monthPeriod
    ) {
        $this->queue = config('emails.notifications.monthly_sales_report.queue', 'report-emails');
        $this->delay = config('emails.notifications.monthly_sales_report.delay', 0);
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
            config('emails.features.monthly_reports', true) &&
            config('emails.notifications.monthly_sales_report.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.monthly_sales_report.template',
                          'emails.admin.monthly-sales-report');

        return (new MailMessage)
            ->subject("📈 Reporte Ejecutivo Mensual - {$this->monthPeriod}")
            ->view($template, [
                'reportData' => $this->reportData,
                'monthPeriod' => $this->monthPeriod,
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
            'type' => 'monthly_sales_report',
            'month_period' => $this->monthPeriod,
            'total_sales' => $this->reportData['sales']['total_sales'] ?? 0,
            'total_revenue' => $this->reportData['sales']['total_revenue'] ?? 0,
            'new_customers' => $this->reportData['customers']['new_users'] ?? 0,
            'growth_rate' => $this->reportData['growth']['revenue_growth_rate'] ?? 0,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('MonthlySalesReport notification failed', [
            'month_period' => $this->monthPeriod,
            'report_data_summary' => [
                'sales_count' => $this->reportData['sales']['total_sales'] ?? 0,
                'revenue' => $this->reportData['sales']['total_revenue'] ?? 0,
            ],
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}


