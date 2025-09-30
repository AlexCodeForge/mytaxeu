<?php

declare(strict_types=1);

namespace App\Mailboxes;

use App\Models\CustomerConversation;
use App\Models\CustomerMessage;
use App\Models\User;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomerSupportMailbox
{
    public function __invoke(InboundEmail $email): void
    {
        Log::info('CustomerSupportMailbox processing email', [
            'from' => $email->from(),
            'subject' => $email->subject(),
            'message_id' => $email->id()
        ]);

        try {
            // Find or create conversation
            $conversation = $this->findOrCreateConversation($email);

            // Create message
            $message = $this->createMessage($conversation, $email);

            // Handle attachments
            $this->processAttachments($message, $email);

            // Update conversation last message time
            $conversation->update(['last_message_at' => now()]);

            Log::info('CustomerSupportMailbox successfully processed email', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id
            ]);

        } catch (\Exception $e) {
            Log::error('CustomerSupportMailbox failed to process email', [
                'error' => $e->getMessage(),
                'from' => $email->from(),
                'subject' => $email->subject(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function findOrCreateConversation(InboundEmail $email): CustomerConversation
    {
        // Clean subject for matching
        $cleanSubject = $this->extractSubject($email->subject());

        // Look for existing conversation based on email and subject similarity
        $conversation = CustomerConversation::where('customer_email', $email->from())
            ->where(function($query) use ($cleanSubject) {
                $query->where('subject', 'like', '%' . $cleanSubject . '%')
                      ->orWhere('subject', $cleanSubject);
            })
            ->where('status', '!=', 'closed')
            ->orderBy('last_message_at', 'desc')
            ->first();

        if (!$conversation) {
            // Try to link to existing user
            $user = User::where('email', $email->from())->first();

            $conversation = CustomerConversation::create([
                'user_id' => $user?->id,
                'customer_email' => $email->from(),
                'customer_name' => $email->fromName(),
                'subject' => $email->subject(),
                'status' => 'open',
                'priority' => $this->determinePriority($email),
                'last_message_at' => now(),
            ]);

            Log::info('Created new customer conversation', [
                'conversation_id' => $conversation->id,
                'customer_email' => $email->from(),
                'subject' => $email->subject()
            ]);
        } else {
            Log::info('Found existing conversation', [
                'conversation_id' => $conversation->id,
                'customer_email' => $email->from()
            ]);
        }

        return $conversation;
    }

    private function createMessage(CustomerConversation $conversation, InboundEmail $email): CustomerMessage
    {
        return CustomerMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => $email->id(),
            'sender_email' => $email->from(),
            'sender_name' => $email->fromName(),
            'sender_type' => 'customer',
            'subject' => $email->subject(),
            'body_text' => $email->text(),
            'body_html' => $email->html(),
            'sent_at' => $email->date(),
        ]);
    }

    private function processAttachments(CustomerMessage $message, InboundEmail $email): void
    {
        foreach ($email->attachments() as $attachment) {
            try {
                $filename = $attachment->getFilename();
                if (empty($filename)) {
                    $filename = 'attachment_' . time();
                }

                $storedName = time() . '_' . uniqid() . '_' . $filename;
                $path = "customer-emails/attachments/{$message->conversation_id}/{$storedName}";

                // Get attachment content
                $content = $attachment->getContent();

                // Save to storage
                Storage::disk('local')->put($path, $content);

                // Create attachment record
                $message->attachments()->create([
                    'original_name' => $filename,
                    'stored_name' => $storedName,
                    'file_path' => $path,
                    'file_size' => strlen($content),
                    'mime_type' => $attachment->getContentType() ?? 'application/octet-stream',
                ]);

                Log::info('Processed email attachment', [
                    'message_id' => $message->id,
                    'filename' => $filename,
                    'path' => $path,
                    'size' => strlen($content)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to process attachment', [
                    'message_id' => $message->id,
                    'filename' => $filename ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                // Continue processing other attachments
            }
        }
    }

    private function extractSubject(string $subject): string
    {
        // Remove Re:, Fwd:, etc. and clean up subject
        $cleaned = preg_replace('/^(Re:|Fwd?:|AW:|SV:)\s*/i', '', trim($subject));
        return $cleaned ?: $subject;
    }

    private function determinePriority(InboundEmail $email): string
    {
        $subject = strtolower($email->subject());
        $body = strtolower($email->text() ?: $email->html() ?: '');

        // Check for urgent keywords
        $urgentKeywords = ['urgent', 'emergency', 'critical', 'asap', 'immediately'];
        $highKeywords = ['important', 'priority', 'please help', 'problem'];

        foreach ($urgentKeywords as $keyword) {
            if (str_contains($subject, $keyword) || str_contains($body, $keyword)) {
                return 'urgent';
            }
        }

        foreach ($highKeywords as $keyword) {
            if (str_contains($subject, $keyword) || str_contains($body, $keyword)) {
                return 'high';
            }
        }

        return 'normal';
    }
}
