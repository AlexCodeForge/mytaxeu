<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CustomerEmails;

use App\Models\CustomerConversation;
use App\Models\CustomerMessage;
use App\Mail\CustomerReplyMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;

#[Layout('layouts.panel')]
#[Title('Conversación con Cliente')]
class Show extends Component
{
    public CustomerConversation $conversation;

    #[Validate('required|string|min:10')]
    public string $replyBody = '';

    #[Validate('required|string|max:500')]
    public string $replySubject = '';

    public bool $isReplying = false;
    public bool $isSending = false;

    public function mount(CustomerConversation $conversation): void
    {
        $this->conversation = $conversation->load([
            'messages' => fn($query) => $query->orderBy('created_at', 'desc'),
            'messages.attachments',
            'user',
            'assignedAdmin'
        ]);
        $this->replySubject = 'Re: ' . $this->conversation->subject;

        // Mark all customer messages as read
        $this->conversation->messages()
            ->where('sender_type', 'customer')
            ->where('is_read', false)
            ->each(fn($message) => $message->markAsRead());

        Log::info('CustomerEmails Show component mounted', [
            'conversation_id' => $this->conversation->id,
            'admin_user' => auth()->id()
        ]);
    }

    public function render()
    {
        return view('livewire.admin.customer-emails.show');
    }

    public function toggleReply(): void
    {
        $this->isReplying = !$this->isReplying;
        if (!$this->isReplying) {
            $this->replyBody = '';
        }
    }

    public function sendReply(): void
    {
        $this->validate();

        $this->isSending = true;

        try {
            Log::info('Admin sending reply to customer', [
                'conversation_id' => $this->conversation->id,
                'admin_user' => auth()->id(),
                'subject' => $this->replySubject
            ]);

            // Create the message record
            $message = CustomerMessage::create([
                'conversation_id' => $this->conversation->id,
                'sender_email' => auth()->user()->email,
                'sender_name' => auth()->user()->name,
                'sender_type' => 'admin',
                'admin_user_id' => auth()->id(),
                'subject' => $this->replySubject,
                'body_text' => strip_tags($this->replyBody),
                'body_html' => $this->replyBody,
                'sent_at' => now(),
            ]);

            // Send the email
            Mail::to($this->conversation->customer_email)
                ->send(new CustomerReplyMail($this->conversation, $message));

            // Update conversation
            $this->conversation->update([
                'last_message_at' => now(),
                'status' => 'in_progress'
            ]);

            // Reset form
            $this->replyBody = '';
            $this->isReplying = false;

            // Refresh conversation with proper ordering
            $this->conversation = $this->conversation->fresh([
                'messages' => fn($query) => $query->orderBy('created_at', 'desc'),
                'messages.attachments',
                'user',
                'assignedAdmin'
            ]);

            session()->flash('success', 'Respuesta enviada correctamente');

            Log::info('Admin reply sent successfully', [
                'conversation_id' => $this->conversation->id,
                'message_id' => $message->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin reply', [
                'conversation_id' => $this->conversation->id,
                'admin_user' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            session()->flash('error', 'Error al enviar la respuesta: ' . $e->getMessage());
        } finally {
            $this->isSending = false;
        }
    }

    public function updateStatus(string $status): void
    {
        $this->conversation->updateStatus($status);
        $this->conversation->refresh();
        session()->flash('success', 'Estado actualizado correctamente');
    }

    public function assignToMe(): void
    {
        $this->conversation->update(['assigned_to' => auth()->id()]);
        $this->conversation->refresh();
        session()->flash('success', 'Conversación asignada a ti');
    }
}
