<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mailboxes\CustomerSupportMailbox;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEmailReceive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-receive
                            {from : The sender email address}
                            {subject : The email subject}
                            {body : The email body text}
                            {--name= : The sender name (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate receiving an email for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $subject = $this->argument('subject');
        $body = $this->argument('body');
        $name = $this->option('name') ?: null;

        $this->info("🔄 Simulating email reception...");
        $this->info("From: {$from}" . ($name ? " ({$name})" : ""));
        $this->info("Subject: {$subject}");
        $this->info("Body: " . substr($body, 0, 100) . (strlen($body) > 100 ? '...' : ''));

        try {
            // Directly create conversation and message (simulating email processing)
            $conversation = \App\Models\CustomerConversation::where('customer_email', $from)
                ->where('subject', 'like', '%' . $this->extractSubject($subject) . '%')
                ->where('status', '!=', 'closed')
                ->first();

            if (!$conversation) {
                // Check if user exists
                $user = \App\Models\User::where('email', $from)->first();

                $conversation = \App\Models\CustomerConversation::create([
                    'user_id' => $user?->id,
                    'customer_email' => $from,
                    'customer_name' => $name,
                    'subject' => $subject,
                    'status' => 'open',
                    'priority' => $this->determinePriority($subject . ' ' . $body),
                    'last_message_at' => now(),
                ]);

                $this->info("📝 Created new conversation ID: {$conversation->id}");
            } else {
                $this->info("📝 Using existing conversation ID: {$conversation->id}");
            }

            // Create the message
            $message = \App\Models\CustomerMessage::create([
                'conversation_id' => $conversation->id,
                'message_id' => 'test-' . uniqid(),
                'sender_email' => $from,
                'sender_name' => $name,
                'sender_type' => 'customer',
                'subject' => $subject,
                'body_text' => $body,
                'body_html' => '<p>' . nl2br(e($body)) . '</p>',
                'sent_at' => now(),
            ]);

            // Update conversation last message time
            $conversation->update(['last_message_at' => now()]);

            $this->info("✅ Email processed successfully!");
            $this->info("📧 Message ID: {$message->id}");
            $this->info("🔍 Check the admin panel at: https://mytaxeu.alexcodeforge.com/admin/customer-emails");

            Log::info('Test email processed via CLI command', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'from' => $from,
                'subject' => $subject
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error processing email: " . $e->getMessage());
            Log::error('Test email processing failed', [
                'error' => $e->getMessage(),
                'from' => $from,
                'subject' => $subject
            ]);

            return self::FAILURE;
        }
    }

    private function extractSubject(string $subject): string
    {
        return preg_replace('/^(Re:|Fwd?:|AW:|SV:)\s*/i', '', trim($subject)) ?: $subject;
    }

    private function determinePriority(string $content): string
    {
        $content = strtolower($content);

        $urgentKeywords = ['urgent', 'emergency', 'critical', 'asap', 'immediately', 'urgente', 'emergencia'];
        $highKeywords = ['important', 'priority', 'please help', 'problem', 'importante', 'prioridad', 'problema'];

        foreach ($urgentKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return 'urgent';
            }
        }

        foreach ($highKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return 'high';
            }
        }

        return 'normal';
    }
}
