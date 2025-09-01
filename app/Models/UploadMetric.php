<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UploadMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'upload_id',
        'file_name',
        'file_size_bytes',
        'line_count',
        'processing_started_at',
        'processing_completed_at',
        'processing_duration_seconds',
        'credits_consumed',
        'status',
        'error_message',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'line_count' => 'integer',
        'processing_duration_seconds' => 'integer',
        'credits_consumed' => 'integer',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * Get the user that owns the upload metric.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the upload that this metric belongs to.
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Check if the metric is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the metric is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the metric is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the metric is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 0) . ' ' . $units[$i];
    }

    /**
     * Scope a query to only include metrics for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include metrics with a specific status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include metrics created between dates.
     */
    public function scopeCreatedBetween(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get the current month usage for a specific user.
     */
    public static function getCurrentMonthUsageForUser(int $userId): int
    {
        return self::forUser($userId)
            ->createdBetween(now()->startOfMonth(), now()->endOfMonth())
            ->sum('line_count');
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function (UploadMetric $uploadMetric): void {
            // Calculate processing duration when both start and end times are set, but only if not already set
            if ($uploadMetric->processing_started_at &&
                $uploadMetric->processing_completed_at &&
                $uploadMetric->processing_duration_seconds === null) {
                $uploadMetric->processing_duration_seconds = $uploadMetric->processing_started_at
                    ->diffInSeconds($uploadMetric->processing_completed_at);
            }
        });
    }
}
