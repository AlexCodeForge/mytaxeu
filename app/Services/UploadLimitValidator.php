<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserUploadLimit;
use App\Models\IpUploadTracking;
use Illuminate\Http\Request;

class UploadLimitValidator
{
    public const FREE_TIER_LINE_LIMIT = 100;

    /**
     * Validate if a user can upload a file with the given line count
     *
     * @param User|null $user The user attempting upload (null for anonymous)
     * @param int $lineCount Number of lines in the CSV file
     * @param string|null $ipAddress IP address for anonymous users
     * @return array{allowed: bool, reason?: string, limit: int, current_usage?: int}
     */
    public function validateUpload(?User $user, int $lineCount, ?string $ipAddress = null): array
    {
        if ($user) {
            return $this->validateUserUpload($user, $lineCount);
        }
        
        if ($ipAddress) {
            return $this->validateIpUpload($ipAddress, $lineCount);
        }
        
        return [
            'allowed' => false,
            'reason' => 'Unable to identify user or IP address',
            'limit' => self::FREE_TIER_LINE_LIMIT,
        ];
    }

    /**
     * Validate upload for authenticated user
     */
    private function validateUserUpload(User $user, int $lineCount): array
    {
        // Check for custom user limits first
        $userLimit = $this->getUserLimit($user);
        $limit = $userLimit ? $userLimit->csv_line_limit : self::FREE_TIER_LINE_LIMIT;

        if ($lineCount > $limit) {
            return [
                'allowed' => false,
                'reason' => $userLimit 
                    ? 'File exceeds your current upload limit'
                    : 'File exceeds free tier limit',
                'limit' => $limit,
                'is_custom_limit' => (bool) $userLimit,
            ];
        }

        return [
            'allowed' => true,
            'limit' => $limit,
            'is_custom_limit' => (bool) $userLimit,
        ];
    }

    /**
     * Validate upload for anonymous user by IP address
     */
    private function validateIpUpload(string $ipAddress, int $lineCount): array
    {
        $limit = self::FREE_TIER_LINE_LIMIT;

        if ($lineCount > $limit) {
            return [
                'allowed' => false,
                'reason' => 'File exceeds free tier limit for anonymous users',
                'limit' => $limit,
            ];
        }

        return [
            'allowed' => true,
            'limit' => $limit,
        ];
    }

    /**
     * Get active user upload limit
     */
    public function getUserLimit(User $user): ?UserUploadLimit
    {
        return $user->uploadLimits()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get current limit for a user
     */
    public function getCurrentLimit(User $user): int
    {
        $userLimit = $this->getUserLimit($user);
        return $userLimit ? $userLimit->csv_line_limit : self::FREE_TIER_LINE_LIMIT;
    }

    /**
     * Get IP upload tracking record
     */
    public function getIpTracking(string $ipAddress): ?IpUploadTracking
    {
        return IpUploadTracking::where('ip_address', $ipAddress)->first();
    }

    /**
     * Record successful upload for tracking
     */
    public function recordUpload(?User $user, int $lineCount, ?string $ipAddress = null): void
    {
        if (!$user && $ipAddress) {
            // Record IP-based upload
            $tracking = IpUploadTracking::findOrCreateForIp($ipAddress);
            $tracking->incrementUsage($lineCount);
        }
        
        // Note: User-based uploads are tracked via the uploads table relationship
        // No additional tracking needed for authenticated users currently
    }

    /**
     * Check if user has premium subscription (placeholder for future implementation)
     */
    public function hasPremiumSubscription(User $user): bool
    {
        // TODO: Implement when subscription tiers are defined
        // This would check if user has an active paid subscription
        return false;
    }

    /**
     * Get limit information for display
     */
    public function getLimitInfo(?User $user, ?string $ipAddress = null): array
    {
        if ($user) {
            $userLimit = $this->getUserLimit($user);
            $limit = $userLimit ? $userLimit->csv_line_limit : self::FREE_TIER_LINE_LIMIT;
            
            return [
                'limit' => $limit,
                'is_custom' => (bool) $userLimit,
                'expires_at' => $userLimit?->expires_at,
                'type' => 'user',
            ];
        }
        
        return [
            'limit' => self::FREE_TIER_LINE_LIMIT,
            'is_custom' => false,
            'expires_at' => null,
            'type' => 'ip',
        ];
    }

    /**
     * Create validation result for API responses
     */
    public function createValidationResult(bool $allowed, string $reason = '', array $data = []): array
    {
        return array_merge([
            'allowed' => $allowed,
            'reason' => $reason,
        ], $data);
    }

    /**
     * Extract IP address from request
     */
    public function getIpFromRequest(Request $request): string
    {
        // Check for forwarded IP first (for load balancers/proxies)
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }
        
        // Check for real IP header
        $realIp = $request->header('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }
        
        // Fall back to standard remote address
        return $request->ip() ?? '127.0.0.1';
    }
}

