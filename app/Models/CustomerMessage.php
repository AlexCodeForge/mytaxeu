<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class CustomerMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'sender_email',
        'sender_name',
        'sender_type',
        'admin_user_id',
        'subject',
        'body_text',
        'body_html',
        'is_read',
        'sent_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CustomerConversation::class, 'conversation_id');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    // Mark as read with logging
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            Log::info('CustomerMessage marked as read', [
                'message_id' => $this->id,
                'conversation_id' => $this->conversation_id,
                'admin_user' => auth()->id()
            ]);

            $this->update(['is_read' => true]);
        }
    }

    // Scope for unread messages
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // Scope for customer messages
    public function scopeFromCustomer($query)
    {
        return $query->where('sender_type', 'customer');
    }

    // Scope for admin messages
    public function scopeFromAdmin($query)
    {
        return $query->where('sender_type', 'admin');
    }

    // Get display body (prefer HTML, fallback to text)
    public function getDisplayBodyAttribute(): string
    {
        return $this->body_html ?: $this->body_text ?: '';
    }

    // Get preview text (first 150 characters)
    public function getPreviewAttribute(): string
    {
        $text = strip_tags($this->body_text ?: $this->body_html ?: '');
        return strlen($text) > 150 ? substr($text, 0, 147) . '...' : $text;
    }

    // Get clean body without quoted text
    public function getCleanBodyAttribute(): string
    {
        $body = $this->body_html ?: $this->body_text ?: '';

        // Remove quoted text patterns common in email replies
        $body = $this->removeQuotedText($body);

        return $body;
    }

    // Remove quoted text from email replies
    private function removeQuotedText(string $text): string
    {
        // If it's HTML, try to extract content before quoted sections
        if (strip_tags($text) !== $text) {
            // Remove gmail_quote divs and everything after them
            $text = preg_replace('/<div[^>]*class="gmail_quote[^>]*>.*$/is', '', $text);
            $text = preg_replace('/<div[^>]*class="gmail_attr[^>]*>.*$/is', '', $text);

            // Remove everything after "On ... wrote:" pattern
            $text = preg_replace('/On\s+.+?(at|,).+?wrote:.*$/is', '', $text);

            // Remove blockquotes
            $text = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/is', '', $text);

            // Remove hr tags that often separate quoted content
            $text = preg_replace('/<hr[^>]*>.*$/is', '', $text);
        } else {
            // Plain text handling
            // Remove everything after "On ... wrote:"
            $text = preg_replace('/On\s+.+?(at|,).+?wrote:.*/is', '', $text);

            // Remove lines starting with ">"
            $text = preg_replace('/^>.*$/m', '', $text);

            // Remove forwarded headers
            $text = preg_replace('/From:.*?Subject:.*/is', '', $text);
        }

        // Clean up excessive whitespace and empty tags
        $text = preg_replace('/<div[^>]*>\s*<\/div>/i', '', $text);
        $text = preg_replace('/(<br\s*\/?>\s*)+$/i', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }
}
