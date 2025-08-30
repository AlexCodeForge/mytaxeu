<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\UploadLimitValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUploadLimits
{
    public function __construct(
        private UploadLimitValidator $limitValidator
    ) {}

    /**
     * Handle an incoming request.
     *
     * This middleware provides basic IP tracking and rate limiting for upload endpoints.
     * Detailed validation is handled in the Form Request classes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to upload-related routes
        if (!$this->shouldCheckLimits($request)) {
            return $next($request);
        }

        $user = $request->user();
        $ipAddress = $this->limitValidator->getIpFromRequest($request);

        // For anonymous users, we track their IP for basic rate limiting
        if (!$user) {
            $this->trackAnonymousRequest($ipAddress);
        }

        // Add limit information to the request for use in views/responses
        $limitInfo = $this->limitValidator->getLimitInfo($user, $ipAddress);
        $request->merge(['upload_limit_info' => $limitInfo]);

        return $next($request);
    }

    /**
     * Determine if this request should be checked for upload limits
     */
    private function shouldCheckLimits(Request $request): bool
    {
        $uploadRoutes = [
            'uploads/*',
            'api/uploads/*',
            '*/upload*',
        ];

        foreach ($uploadRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Track anonymous user request for basic monitoring
     */
    private function trackAnonymousRequest(string $ipAddress): void
    {
        // For now, just ensure IP tracking record exists
        // Detailed usage tracking happens in the upload process
        $this->limitValidator->getIpTracking($ipAddress);
    }
}
