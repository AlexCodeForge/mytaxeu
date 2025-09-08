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

class SubscriptionRenewalReminder extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels, Dispatchable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public array $subscriptionData,
        public array $usageStats,
        public int $daysUntilRenewal = 7
    ) {
        $this->queue = config('emails.notifications.subscription_renewal_reminder.queue', 'emails');
        $this->delay = config('emails.notifications.subscription_renewal_reminder.delay', 0);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (config('emails.features.subscription_emails', true) &&
            config('emails.notifications.subscription_renewal_reminder.enabled', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $template = config('emails.notifications.subscription_renewal_reminder.template',
                          'emails.users.subscription-renewal-reminder');

        $subject = $this->daysUntilRenewal === 1
            ? '⚠️ Tu suscripción se renueva mañana'
            : "🔔 Tu suscripción se renueva en {$this->daysUntilRenewal} días";

        return (new MailMessage)
            ->subject($subject)
            ->view($template, [
                'user' => $notifiable,
                'subscription' => $this->subscriptionData,
                'usage' => $this->usageStats,
                'daysUntilRenewal' => $this->daysUntilRenewal,
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
            'type' => 'subscription_renewal_reminder',
            'subscription_plan' => $this->subscriptionData['plan_name'] ?? '',
            'renewal_date' => $this->subscriptionData['next_billing_date'] ?? null,
            'days_until_renewal' => $this->daysUntilRenewal,
            'files_processed_this_period' => $this->usageStats['files_processed'] ?? 0,
            'credits_used_this_period' => $this->usageStats['credits_used'] ?? 0,
            'timestamp' => now(),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SubscriptionRenewalReminder notification failed', [
            'subscription_data' => $this->subscriptionData,
            'usage_stats' => $this->usageStats,
            'days_until_renewal' => $this->daysUntilRenewal,
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
        return hash('sha256', $notifiable->email . config('app.key') . 'renewal_reminders');
    }
}


