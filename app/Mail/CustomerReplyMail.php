<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CustomerConversation;
use App\Models\CustomerMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $messageBody;
    public $customerName;
    public $adminName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public CustomerConversation $conversation,
        public CustomerMessage $message
    ) {
        $this->queue = 'emails';

        // Store the message body as a string to prevent serialization issues
        $this->messageBody = $message->body_html ?: $message->body_text ?: 'Sin contenido';
        $this->customerName = $conversation->customer_name ?? 'Cliente';
        $this->adminName = $message->adminUser?->name ?? 'Soporte MyTaxEU';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message->subject,
            replyTo: 'no-reply@alexcodeforge.com',
            from: 'no-reply@alexcodeforge.com',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-reply',
            with: [
                'messageBody' => $this->messageBody,
                'customerName' => $this->customerName,
                'adminName' => $this->adminName,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
