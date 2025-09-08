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
use App\Services\EmailConfigService;

class SaleNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $customerData,
        public array $saleData,
        public array $revenueData
    ) {
        $this->queue = EmailConfigService::getNotificationQueue('sale_notification');
        $this->delay = EmailConfigService::getNotificationDelay('sale_notification');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (EmailConfigService::isFeatureEnabled('admin_notifications') &&
            EmailConfigService::isNotificationEnabled('sale_notification')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailConfigService::getNotificationTemplate('sale_notification');

        return (new MailMessage)
            ->subject('💰 Nueva Venta Realizada - MyTaxEU')
            ->view($template, [
                'customer' => $this->customerData,
                'sale' => $this->saleData,
                'revenue' => $this->revenueData,
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
            'type' => 'sale_notification',
            'customer_email' => $this->customerData['email'] ?? '',
            'customer_name' => $this->customerData['name'] ?? '',
            'sale_amount' => $this->saleData['amount'] ?? 0,
            'sale_currency' => $this->saleData['currency'] ?? 'EUR',
            'plan_name' => $this->saleData['plan_name'] ?? '',
            'revenue_total' => $this->revenueData['total'] ?? 0,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SaleNotification failed', [
            'customer_data' => $this->customerData,
            'sale_data' => $this->saleData,
            'revenue_data' => $this->revenueData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

