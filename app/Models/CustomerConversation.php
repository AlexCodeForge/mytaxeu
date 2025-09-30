<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class CustomerConversation extends Model
{
    protected $fillable = [
        'user_id',
        'customer_email',
        'customer_name',
        'subject',
        'status',
        'priority',
        'assigned_to',
        'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class, 'conversation_id')
            ->orderBy('created_at', 'desc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(CustomerMessage::class, 'conversation_id')->latest('sent_at');
    }

    // Debug logging for status changes
    public function updateStatus(string $status): void
    {
        Log::info('CustomerConversation status update', [
            'conversation_id' => $this->id,
            'old_status' => $this->status,
            'new_status' => $status,
            'admin_user' => auth()->id()
        ]);

        $this->update(['status' => $status]);
    }

    // Scope for unread conversations
    public function scopeWithUnreadMessages($query)
    {
        return $query->whereHas('messages', function ($q) {
            $q->where('sender_type', 'customer')
              ->where('is_read', false);
        });
    }

    // Scope for assigned conversations
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    // Check if conversation has unread messages
    public function hasUnreadMessages(): bool
    {
        return $this->messages()
            ->where('sender_type', 'customer')
            ->where('is_read', false)
            ->exists();
    }
}
