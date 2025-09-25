<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\EmailConfigService;

class PurchaseThankYou extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $customerData,
        public array $purchaseData,
        public array $subscriptionData,
        public array $creditsData
    ) {
        $this->queue = EmailConfigService::getNotificationQueue('purchase_thank_you');
        $this->delay = EmailConfigService::getNotificationDelay('purchase_thank_you');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (EmailConfigService::isFeatureEnabled('subscription_emails') &&
            EmailConfigService::isNotificationEnabled('purchase_thank_you')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailConfigService::getNotificationTemplate('purchase_thank_you');
        if (!$template) {
            $template = 'emails.users.purchase-thank-you';
        }

        return (new MailMessage)
            ->subject('🎉 ¡Gracias por tu compra! - Bienvenido a MyTaxEU')
            ->view($template, [
                'user' => $notifiable,
                'customer' => $this->customerData,
                'purchase' => $this->purchaseData,
                'subscription' => $this->subscriptionData,
                'credits' => $this->creditsData,
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
            'customer_data' => $this->customerData,
            'purchase_data' => $this->purchaseData,
            'subscription_data' => $this->subscriptionData,
            'credits_data' => $this->creditsData,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PurchaseThankYou notification failed', [
            'customer_data' => $this->customerData,
            'purchase_data' => $this->purchaseData,
            'subscription_data' => $this->subscriptionData,
            'credits_data' => $this->creditsData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
