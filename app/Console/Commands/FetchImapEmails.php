<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mailboxes\CustomerSupportMailbox;
use App\Models\CustomerConversation;
use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class FetchImapEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:fetch-imap
                            {--dry-run : Show what would be processed without actually processing}
                            {--limit=10 : Maximum number of emails to process}
                            {--mark-seen : Mark emails as seen after processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from IMAP server and process them as customer support emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $markSeen = $this->option('mark-seen');

        $this->info('🔄 Starting IMAP email fetch...');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No emails will be processed');
        }

        try {
            // Create IMAP connection using your Mailcow settings
            $cm = new ClientManager();

            // Configure IMAP client for Mailcow
            $client = $cm->make([
                'host' => 'mail.alexcodeforge.com',
                'port' => 993,
                'encryption' => 'ssl',
                'validate_cert' => true,
                'username' => 'no-reply@alexcodeforge.com',
                'password' => 'TempPass123!',
                'protocol' => 'imap'
            ]);

            $this->info('📡 Connecting to IMAP server...');
            $client->connect();

            // Get INBOX folder
            $folder = $client->getFolder('INBOX');

            // Get unread messages (sorted by date descending - newest first)
            $messages = $folder->query()->whereUnseen()->limit($limit, 1, 'DESC')->get();

            $this->info("📬 Found {$messages->count()} unread emails");

            if ($messages->count() === 0) {
                $this->info('✅ No new emails to process');
                return self::SUCCESS;
            }

            $processed = 0;
            $errors = 0;

            foreach ($messages as $message) {
                try {
                    $from = $message->getFrom()[0]->mail ?? 'unknown';
                    $fromName = $message->getFrom()[0]->personal ?? null;
                    $subject = $message->getSubject()[0] ?? 'No Subject';
                    $bodyText = $message->getTextBody();
                    $bodyHtml = $message->getHTMLBody();
                    $sentAt = $message->getDate()[0];

                    $this->line("📧 Processing: {$from} - {$subject}");

                    if (!$dryRun) {
                        // Check if we already processed this message
                        $messageId = $message->getMessageId()[0] ?? null;

                        if ($messageId && CustomerMessage::where('message_id', $messageId)->exists()) {
                            $this->warn("⚠️  Email already processed: {$messageId}");
                            continue;
                        }

                        // Process the email (similar to our test command)
                        $this->processEmail($from, $fromName, $subject, $bodyText, $bodyHtml, $sentAt, $messageId);

                        // Mark as seen if requested
                        if ($markSeen) {
                            $message->setFlag('Seen');
                        }

                        $processed++;
                    } else {
                        $this->info("  📝 Would process: {$from} - " . substr($subject, 0, 50));
                    }

                } catch (\Exception $e) {
                    $this->error("❌ Error processing email: " . $e->getMessage());
                    $errors++;
                    Log::error('IMAP email processing error', [
                        'error' => $e->getMessage(),
                        'email_from' => $from ?? 'unknown'
                    ]);
                }
            }

            if (!$dryRun) {
                $this->info("✅ Processed {$processed} emails successfully");
                if ($errors > 0) {
                    $this->warn("⚠️  {$errors} emails had errors");
                }
                $this->info("🔍 Check admin panel: https://mytaxeu.alexcodeforge.com/admin/customer-emails");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ IMAP connection failed: " . $e->getMessage());
            Log::error('IMAP connection error', [
                'error' => $e->getMessage()
            ]);
            return self::FAILURE;
        }
    }

    private function processEmail(
        string $from,
        ?string $fromName,
        string $subject,
        ?string $bodyText,
        ?string $bodyHtml,
        $sentAt,
        ?string $messageId
    ): void {
        // Find or create conversation
        $conversation = CustomerConversation::where('customer_email', $from)
            ->where('subject', 'like', '%' . $this->extractSubject($subject) . '%')
            ->where('status', '!=', 'closed')
            ->first();

        if (!$conversation) {
            // Check if user exists
            $user = User::where('email', $from)->first();

            $conversation = CustomerConversation::create([
                'user_id' => $user?->id,
                'customer_email' => $from,
                'customer_name' => $fromName,
                'subject' => $subject,
                'status' => 'open',
                'priority' => $this->determinePriority($subject . ' ' . ($bodyText ?: $bodyHtml ?: '')),
                'last_message_at' => $sentAt ? \Carbon\Carbon::parse($sentAt) : now(),
            ]);
        }

        // Create message
        CustomerMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'sender_email' => $from,
            'sender_name' => $fromName,
            'sender_type' => 'customer',
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'sent_at' => $sentAt ? \Carbon\Carbon::parse($sentAt) : now(),
        ]);

        // Update conversation
        $conversation->update([
            'last_message_at' => $sentAt ? \Carbon\Carbon::parse($sentAt) : now()
        ]);

        Log::info('IMAP email processed successfully', [
            'conversation_id' => $conversation->id,
            'from' => $from,
            'subject' => $subject
        ]);
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
