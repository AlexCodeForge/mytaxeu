<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UsageLimitExceededException;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsageMeteringService
{
    // Free tier monthly limit (lines) - only for users with NO credits
    private const FREE_TIER_MONTHLY_LIMIT = 100;

    /**
     * Track the start of an upload process.
     */
    public function trackUploadStart(Upload $upload, int $lineCount, int $fileSizeBytes): UploadMetric
    {
        // Check if user is within tier limits
        if (!$this->canProcessLines($upload->user, $lineCount)) {
            throw new UsageLimitExceededException(
                "Processing {$lineCount} lines would exceed your monthly limit of " . self::FREE_TIER_MONTHLY_LIMIT . " lines."
            );
        }

        $uploadMetric = UploadMetric::create([
            'user_id' => $upload->user_id,
            'upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'file_size_bytes' => $fileSizeBytes,
            'line_count' => $lineCount,
            'processing_started_at' => now(),
            'status' => UploadMetric::STATUS_PROCESSING,
        ]);

        Log::info('Upload tracking started', [
            'upload_id' => $upload->id,
            'user_id' => $upload->user_id,
            'line_count' => $lineCount,
            'file_size_bytes' => $fileSizeBytes,
        ]);

        return $uploadMetric;
    }

    /**
     * Track successful processing completion.
     */
    public function trackProcessingCompletion(UploadMetric $uploadMetric, bool $creditConsumed, int $creditsConsumed = 1): void
    {
        $uploadMetric->update([
            'processing_completed_at' => now(),
            'status' => UploadMetric::STATUS_COMPLETED,
            'credits_consumed' => $creditConsumed ? $creditsConsumed : 0,
        ]);

        // Update user usage counters
        $this->updateUserUsageCounters($uploadMetric->user, $uploadMetric);

        Log::info('Processing completion tracked', [
            'upload_id' => $uploadMetric->upload_id,
            'user_id' => $uploadMetric->user_id,
            'credits_consumed' => $uploadMetric->credits_consumed,
            'line_count' => $uploadMetric->line_count,
        ]);
    }

    /**
     * Track processing failure.
     */
    public function trackProcessingFailure(UploadMetric $uploadMetric, string $errorMessage): void
    {
        $uploadMetric->update([
            'processing_completed_at' => now(),
            'status' => UploadMetric::STATUS_FAILED,
            'error_message' => $errorMessage,
            'credits_consumed' => 0,
        ]);

        Log::warning('Processing failure tracked', [
            'upload_id' => $uploadMetric->upload_id,
            'user_id' => $uploadMetric->user_id,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update user's usage counters.
     */
    public function updateUserUsageCounters(User $user, UploadMetric $uploadMetric): void
    {
        // Only update counters for successfully completed uploads
        if ($uploadMetric->status !== UploadMetric::STATUS_COMPLETED) {
            return;
        }

        $user->increment('total_lines_processed', $uploadMetric->line_count);
        $user->increment('current_month_usage', $uploadMetric->line_count);

        Log::info('User usage counters updated', [
            'user_id' => $user->id,
            'lines_added' => $uploadMetric->line_count,
            'new_total' => $user->fresh()->total_lines_processed,
            'new_monthly' => $user->fresh()->current_month_usage,
        ]);
    }

    /**
     * Get current month usage for a user.
     */
    public function getCurrentMonthUsage(User $user): int
    {
        // Use the cached value from user table if available and recent
        if ($user->current_month_usage !== null) {
            return $user->current_month_usage;
        }

        // Fallback to calculating from upload metrics
        return UploadMetric::getCurrentMonthUsageForUser($user->id);
    }

        /**
     * Check if user can process the specified number of lines.
     * Only applies to free tier users (no credits).
     */
    public function canProcessLines(User $user, int $lineCount): bool
    {
        // Admins have no limits at all
        if ($user->isAdmin()) {
            return true;
        }

        // Users with credits have no line limits - only credit limits apply
        if ($user->credits > 0) {
            return true;
        }

        // Only free tier users (no credits) have line limits
        $currentUsage = $this->getCurrentMonthUsage($user);
        $newTotal = $currentUsage + $lineCount;

        return $newTotal <= self::FREE_TIER_MONTHLY_LIMIT;
    }

    /**
     * Reset monthly usage for a user.
     */
    public function resetMonthlyUsage(User $user): void
    {
        $user->update([
            'current_month_usage' => 0,
            'usage_reset_date' => now()->toDateString(),
        ]);

        Log::info('Monthly usage reset', [
            'user_id' => $user->id,
            'reset_date' => now()->toDateString(),
        ]);
    }

    /**
     * Get comprehensive usage statistics for a user.
     */
    public function getUserUsageStatistics(User $user): array
    {
        $metrics = UploadMetric::where('user_id', $user->id)->get();

        $completedMetrics = $metrics->where('status', UploadMetric::STATUS_COMPLETED);
        $metricsWithProcessingTime = $completedMetrics->whereNotNull('processing_duration_seconds');

        $stats = [
            'total_lines_processed' => $completedMetrics->sum('line_count'),
            'total_file_size_bytes' => $metrics->sum('file_size_bytes'),
            'total_processing_time_seconds' => $metricsWithProcessingTime->sum('processing_duration_seconds'),
            'total_credits_consumed' => $metrics->sum('credits_consumed'),
            'successful_uploads' => $completedMetrics->count(),
            'failed_uploads' => $metrics->where('status', UploadMetric::STATUS_FAILED)->count(),
            'total_uploads' => $metrics->count(),
            'current_month_usage' => $this->getCurrentMonthUsage($user),
            'remaining_monthly_limit' => max(0, self::FREE_TIER_MONTHLY_LIMIT - $this->getCurrentMonthUsage($user)),
            'average_file_size' => $metrics->count() > 0 ? round((float) $metrics->avg('file_size_bytes')) : 0,
            'average_processing_time' => $metricsWithProcessingTime->count() > 0
                ? round((float) $metricsWithProcessingTime->avg('processing_duration_seconds'))
                : 0,
        ];

        return $stats;
    }



    /**
     * Get usage trends for a user over the specified number of days.
     */
    public function getUsageTrends(User $user, int $days = 30): array
    {
        $endDate = now();
        $startDate = now()->subDays($days);

        $metrics = UploadMetric::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(line_count) as line_count, COUNT(*) as upload_count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return $metrics->toArray();
    }

    /**
     * Get system-wide usage statistics (admin only).
     */
    public function getSystemUsageStatistics(): array
    {
        $totalMetrics = UploadMetric::count();
        $completedMetrics = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)->count();
        $failedMetrics = UploadMetric::where('status', UploadMetric::STATUS_FAILED)->count();

        $totalLinesProcessed = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)
            ->sum('line_count');

        $totalProcessingTime = UploadMetric::whereNotNull('processing_duration_seconds')
            ->sum('processing_duration_seconds');

        $averageProcessingTime = UploadMetric::whereNotNull('processing_duration_seconds')
            ->avg('processing_duration_seconds');

        $totalFileSize = UploadMetric::sum('file_size_bytes');

        // Get credits data
        $totalCreditsConsumed = UploadMetric::sum('credits_consumed') ?? 0;
        $totalCreditsAllocated = \App\Models\CreditTransaction::where('type', 'purchased')->sum('amount') ?? 0;
        $totalCreditsInCirculation = User::sum('credits') ?? 0;

        return [
            'total_uploads' => $totalMetrics,
            'successful_uploads' => $completedMetrics,
            'failed_uploads' => $failedMetrics,
            'success_rate_percentage' => $totalMetrics > 0 ? round(($completedMetrics / $totalMetrics) * 100, 2) : 0,
            'total_lines_processed' => $totalLinesProcessed,
            'total_processing_time_seconds' => (float) $totalProcessingTime,
            'average_processing_time_seconds' => round((float) ($averageProcessingTime ?? 0), 2),
            'total_file_size_bytes' => $totalFileSize,
            'total_file_size_mb' => round((float) $totalFileSize / 1024 / 1024, 2),
            'active_users_count' => User::whereHas('uploadMetrics')->count(),
            'total_credits_consumed' => $totalCreditsConsumed,
            'total_credits_allocated' => $totalCreditsAllocated,
            'total_credits_in_circulation' => $totalCreditsInCirculation,
        ];
    }

        /**
     * Get the monthly limit for a user based on their tier.
     */
    public function getMonthlyLimit(User $user): int
    {
        // Admins have no limits
        if ($user->isAdmin()) {
            return PHP_INT_MAX; // Effectively unlimited
        }

        // Users with credits have no line limits - only credit limits
        if ($user->credits > 0) {
            return PHP_INT_MAX; // Effectively unlimited
        }

        // Only free tier users (no credits) have line limits
        return self::FREE_TIER_MONTHLY_LIMIT;
    }

    /**
     * Get users approaching their monthly limits.
     */
    public function getUsersApproachingLimits(float $threshold = 0.8): array
    {
        $limitThreshold = (int) (self::FREE_TIER_MONTHLY_LIMIT * $threshold);

        return User::where('current_month_usage', '>=', $limitThreshold)
            ->where('current_month_usage', '<', self::FREE_TIER_MONTHLY_LIMIT)
            ->orderBy('current_month_usage', 'desc')
            ->get(['id', 'name', 'email', 'current_month_usage'])
            ->toArray();
    }

    /**
     * Process monthly usage resets for all users.
     */
    public function processMonthlyResets(): int
    {
        $resetCount = 0;
        $cutoffDate = now()->subMonth()->toDateString();

        $usersToReset = User::where(function ($query) use ($cutoffDate) {
            $query->whereNull('usage_reset_date')
                  ->orWhere('usage_reset_date', '<=', $cutoffDate);
        })->where('current_month_usage', '>', 0);

        foreach ($usersToReset->get() as $user) {
            $this->resetMonthlyUsage($user);
            $resetCount++;
        }

        Log::info('Monthly usage reset completed', [
            'users_reset' => $resetCount,
            'cutoff_date' => $cutoffDate,
        ]);

        return $resetCount;
    }

    /**
     * Export user usage data for CSV download.
     */
    public function exportUserUsageData(User $user, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = UploadMetric::where('user_id', $user->id)
            ->with('upload:id,original_name');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        $metrics = $query->orderBy('created_at', 'desc')->get();

        return $metrics->map(function ($metric) {
            return [
                'file_name' => $metric->file_name,
                'line_count' => $metric->line_count,
                'file_size_bytes' => $metric->file_size_bytes,
                'processing_duration_seconds' => $metric->processing_duration_seconds,
                'credits_consumed' => $metric->credits_consumed,
                'status' => $metric->status,
                'created_at' => $metric->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}
