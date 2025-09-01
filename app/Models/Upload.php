<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'disk',
        'path',
        'transformed_path',
        'size_bytes',
        'csv_line_count',
        'rows_count',
        'status',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'csv_line_count' => 'integer',
        'rows_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_RECEIVED => 'blue',
            self::STATUS_QUEUED => 'yellow',
            self::STATUS_PROCESSING => 'orange',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_RECEIVED => 'Recibido',
            self::STATUS_QUEUED => 'En cola',
            self::STATUS_PROCESSING => 'Procesando',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_FAILED => 'Fallido',
            default => ucfirst($this->status),
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
        ]);
    }

    public function getFileUrl(): ?string
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return null;
    }

    public function getFileContents(): ?string
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->get($this->path);
        }

        return null;
    }

    public function getTransformedFileUrl(): ?string
    {
        if ($this->transformed_path && Storage::disk($this->disk)->exists($this->transformed_path)) {
            return Storage::disk($this->disk)->url($this->transformed_path);
        }

        return null;
    }

    public function getTransformedFileContents(): ?string
    {
        if ($this->transformed_path && Storage::disk($this->disk)->exists($this->transformed_path)) {
            return Storage::disk($this->disk)->get($this->transformed_path);
        }

        return null;
    }

    public function hasTransformedFile(): bool
    {
        return !empty($this->transformed_path) && Storage::disk($this->disk)->exists($this->transformed_path);
    }

    protected static function booted(): void
    {
        static::updated(function (Upload $upload): void {
            // Mark as processed when status changes to completed or failed
            if (in_array($upload->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]) && ! $upload->processed_at) {
                $upload->processed_at = now();
                $upload->saveQuietly(); // Avoid infinite recursion
            }
        });
    }
}
