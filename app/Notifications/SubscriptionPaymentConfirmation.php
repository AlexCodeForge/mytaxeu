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

class SubscriptionPaymentConfirmation extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $paymentData,
        public array $subscriptionData,
        public array $creditsData
    ) {
        $this->queue = EmailConfigService::getNotificationQueue('subscription_payment_confirmation');
        $this->delay = EmailConfigService::getNotificationDelay('subscription_payment_confirmation');
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
            EmailConfigService::isNotificationEnabled('subscription_payment_confirmation')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = EmailConfigService::getNotificationTemplate('subscription_payment_confirmation');

        return (new MailMessage)
            ->subject('🎉 Pago Confirmado - Créditos Añadidos a tu Cuenta')
            ->view($template, [
                'user' => $notifiable,
                'payment' => $this->paymentData,
                'subscription' => $this->subscriptionData,
                'credits' => $this->creditsData,
                'unsubscribeToken' => $this->generateUnsubscribeToken($notifiable),
                'email' => $notifiable->email,
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
            'type' => 'subscription_payment_confirmation',
            'payment_amount' => $this->paymentData['amount'] ?? 0,
            'currency' => $this->paymentData['currency'] ?? 'EUR',
            'credits_added' => $this->creditsData['amount'] ?? 0,
            'subscription_plan' => $this->subscriptionData['plan_name'] ?? '',
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SubscriptionPaymentConfirmation notification failed', [
            'payment_data' => $this->paymentData,
            'subscription_data' => $this->subscriptionData,
            'credits_data' => $this->creditsData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Generate unsubscribe token for the user
     */
    protected function generateUnsubscribeToken(object $notifiable): string
    {
        // In a real implementation, this would generate a secure token
        // For now, return a placeholder
        return hash('sha256', $notifiable->email . config('app.key') . 'subscription_notifications');
    }
}

