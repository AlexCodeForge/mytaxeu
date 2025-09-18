<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Queue\SerializesModels;
use App\Services\EmailConfigService;
use Exception;

class EmailService
{
    use SerializesModels;

    /**
     * Send email immediately (not queued)
     *
     * @param string|array $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param array $options
     * @return bool
     */
    public function sendImmediate(
        string|array $to,
        string $subject,
        string $view,
        array $data = [],
        array $options = []
    ): bool {
        try {
            $mailable = $this->createMailable($to, $subject, $view, $data, $options);

            Mail::send($mailable);

            $this->logEmailSuccess($to, $subject, $view);

            return true;
        } catch (Exception $e) {
            $this->logEmailError($to, $subject, $view, $e);

            return false;
        }
    }

    /**
     * Queue email for background processing
     *
     * @param string|array $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param array $options
     * @return bool
     */
    public function sendQueued(
        string|array $to,
        string $subject,
        string $view,
        array $data = [],
        array $options = []
    ): bool {
        try {
            $mailable = $this->createMailable($to, $subject, $view, $data, $options);

            $delay = $options['delay'] ?? null;
            $queue = $options['queue'] ?? config('emails.queue.default_queue', 'emails');

            if ($delay) {
                Mail::later($delay, $mailable);
            } else {
                Mail::queue($mailable);
            }

            $this->logEmailQueued($to, $subject, $view, $queue);

            return true;
        } catch (Exception $e) {
            $this->logEmailError($to, $subject, $view, $e);

            return false;
        }
    }

    /**
     * Send email to admin addresses
     *
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param array $options
     * @return bool
     */
    public function sendToAdmins(
        string $subject,
        string $view,
        array $data = [],
        array $options = []
    ): bool {
        $adminEmails = EmailConfigService::getAdminEmails();

        if (empty($adminEmails)) {
            Log::warning('No admin email addresses configured');
            return false;
        }

        $success = true;
        foreach ($adminEmails as $adminEmail) {
            $result = $this->sendQueued($adminEmail, $subject, $view, $data, $options);
            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Send bulk emails
     *
     * @param array $recipients Array of ['email' => string, 'data' => array]
     * @param string $subject
     * @param string $view
     * @param array $globalData
     * @param array $options
     * @return array
     */
    public function sendBulk(
        array $recipients,
        string $subject,
        string $view,
        array $globalData = [],
        array $options = []
    ): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $batchSize = $options['batch_size'] ?? config('emails.queue.bulk_batch_size', 50);
        $chunks = array_chunk($recipients, $batchSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $recipient) {
                $email = $recipient['email'];
                $recipientData = array_merge($globalData, $recipient['data'] ?? []);

                try {
                    $success = $this->sendQueued($email, $subject, $view, $recipientData, $options);

                    if ($success) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to queue email for {$email}";
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Exception for {$email}: " . $e->getMessage();
                }
            }

            // Add small delay between batches to prevent overwhelming the queue
            if (count($chunks) > 1) {
                usleep(100000); // 100ms
            }
        }

        Log::info('Bulk email send completed', [
            'total_recipients' => count($recipients),
            'success' => $results['success'],
            'failed' => $results['failed'],
            'subject' => $subject,
            'view' => $view
        ]);

        return $results;
    }

    /**
     * Render email template for preview/testing
     *
     * @param string $view
     * @param array $data
     * @return string
     */
    public function renderTemplate(string $view, array $data = []): string
    {
        try {
            return view($view, $data)->render();
        } catch (Exception $e) {
            Log::error('Email template rendering failed', [
                'view' => $view,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Test email configuration
     *
     * @param string $testEmail
     * @return bool
     */
    public function testConfiguration(string $testEmail): bool
    {
        try {
            $testData = [
                'test_time' => now()->format('Y-m-d H:i:s'),
                'app_name' => config('app.name'),
                'environment' => app()->environment()
            ];

            return $this->sendImmediate(
                $testEmail,
                'MyTaxEU Email Configuration Test',
                'emails.test.configuration',
                $testData
            );
        } catch (Exception $e) {
            Log::error('Email configuration test failed', [
                'email' => $testEmail,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get email delivery statistics
     *
     * @param int $days
     * @return array
     */
    public function getDeliveryStats(int $days = 7): array
    {
        // This would integrate with your logging/monitoring system
        // For now, return basic structure
        return [
            'period_days' => $days,
            'total_sent' => 0,
            'successful_deliveries' => 0,
            'failed_deliveries' => 0,
            'queued_emails' => 0,
            'delivery_rate' => 0.0,
            'last_updated' => now()
        ];
    }

    /**
     * Create mailable instance
     *
     * @param string|array $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param array $options
     * @return EmailMailable
     */
    protected function createMailable(
        string|array $to,
        string $subject,
        string $view,
        array $data,
        array $options
    ): EmailMailable {
        // Ensure email variable is always available in the template
        if (!isset($data['email']) || empty($data['email'])) {
            $data['email'] = is_array($to) ? $to[0] : $to;
        }

        return new EmailMailable($to, $subject, $view, $data, $options);
    }

    /**
     * Log successful email
     */
    protected function logEmailSuccess(string|array $to, string $subject, string $view): void
    {
        Log::info('Email sent successfully', [
            'to' => is_array($to) ? $to : [$to],
            'subject' => $subject,
            'view' => $view,
            'timestamp' => now()
        ]);
    }

    /**
     * Log queued email
     */
    protected function logEmailQueued(
        string|array $to,
        string $subject,
        string $view,
        string $queue
    ): void {
        Log::info('Email queued successfully', [
            'to' => is_array($to) ? $to : [$to],
            'subject' => $subject,
            'view' => $view,
            'queue' => $queue,
            'timestamp' => now()
        ]);
    }

    /**
     * Log email error
     */
    protected function logEmailError(
        string|array $to,
        string $subject,
        string $view,
        Exception $e
    ): void {
        Log::error('Email sending failed', [
            'to' => is_array($to) ? $to : [$to],
            'subject' => $subject,
            'view' => $view,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => now()
        ]);
    }
}

/**
 * Custom Mailable class for flexible email sending
 */
class EmailMailable extends Mailable
{
    public function __construct(
        public string|array $recipient,
        public string $emailSubject,
        public string $emailView,
        public array $emailData,
        public array $emailOptions = []
    ) {
        $this->to($recipient);
        $this->subject($emailSubject);

        // Set additional options
        if (isset($emailOptions['from'])) {
            $this->from($emailOptions['from']);
        }

        if (isset($emailOptions['reply_to'])) {
            $this->replyTo($emailOptions['reply_to']);
        }

        if (isset($emailOptions['cc'])) {
            $this->cc($emailOptions['cc']);
        }

        if (isset($emailOptions['bcc'])) {
            $this->bcc($emailOptions['bcc']);
        }
    }

    public function build()
    {
        return $this->view($this->emailView)
                    ->with($this->emailData);
    }
}

