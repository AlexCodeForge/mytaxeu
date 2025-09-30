<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'original_name',
        'stored_name',
        'file_path',
        'file_size',
        'mime_type'
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(CustomerMessage::class, 'message_id');
    }

    // Get file size in human readable format
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Check if file exists in storage
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    // Get download URL (to be implemented in controller)
    public function getDownloadUrlAttribute(): string
    {
        return route('admin.customer-emails.attachment.download', $this);
    }

    // Check if attachment is an image
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // Get file extension from original name
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }
}
