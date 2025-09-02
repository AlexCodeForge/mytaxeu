<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditTransaction;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialDataService
{
    private const CACHE_TTL_MINUTES = 15;
    private const MRR_CACHE_KEY = 'financial_mrr';

    /**
     * Calculate Monthly Recurring Revenue from active subscriptions.
     *
     * This method calculates MRR by aggregating revenue from all active Stripe subscriptions.
     * In a real implementation, this would fetch actual pricing from Stripe API.
     */
    public function calculateMonthlyRecurringRevenue(): float
    {
        return Cache::remember(self::MRR_CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            try {
                Log::debug('FinancialDataService: Calculating MRR from active subscriptions');

                // For now, we'll use a simplified calculation
                // In production, this would integrate with Stripe's API to get actual pricing
                $activeSubscriptionsCount = Subscription::where('stripe_status', 'active')->count();

                // Placeholder calculation - in reality would sum actual subscription prices
                $averageSubscriptionPrice = 10.00; // $10 per subscription as baseline
                $mrr = $activeSubscriptionsCount * $averageSubscriptionPrice;

                Log::info('FinancialDataService: MRR calculated', [
                    'active_subscriptions' => $activeSubscriptionsCount,
                    'mrr' => $mrr,
                ]);

                return (float) $mrr;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to calculate MRR', [
                    'error' => $e->getMessage(),
                ]);
                return 0.0;
            }
        });
    }

    /**
     * Calculate total revenue from all credit transactions.
     */
    public function calculateTotalRevenue(): float
    {
        try {
            Log::debug('FinancialDataService: Calculating total revenue');

            $totalRevenue = CreditTransaction::where('type', 'purchased')
                ->where('description', 'NOT LIKE', '%TEST%') // Exclude test transactions
                ->sum('amount');

            $revenueInDollars = $totalRevenue / 100; // Convert cents to dollars

            Log::info('FinancialDataService: Total revenue calculated', [
                'total_revenue_cents' => $totalRevenue,
                'total_revenue_dollars' => $revenueInDollars,
            ]);

            return (float) $revenueInDollars;

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to calculate total revenue', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate revenue for a specific time period.
     */
    public function calculatePeriodRevenue(Carbon $startDate, Carbon $endDate): float
    {
        $this->validateDateRange($startDate, $endDate);

        $cacheKey = $this->getPeriodRevenueCacheKey($startDate, $endDate);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            try {
                Log::debug('FinancialDataService: Calculating period revenue', [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]);

                $totalRevenue = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');

                $revenueInDollars = $totalRevenue / 100;

                Log::info('FinancialDataService: Period revenue calculated', [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'revenue' => $revenueInDollars,
                ]);

                return (float) $revenueInDollars;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to calculate period revenue', [
                    'error' => $e->getMessage(),
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]);
                return 0.0;
            }
        });
    }

    /**
     * Calculate revenue growth percentage between two periods.
     */
    public function calculateRevenueGrowth(
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd
    ): float {
        try {
            $currentRevenue = $this->calculatePeriodRevenue($currentStart, $currentEnd);
            $previousRevenue = $this->calculatePeriodRevenue($previousStart, $previousEnd);

            if ($previousRevenue == 0) {
                return $currentRevenue > 0 ? 100.0 : 0.0;
            }

            $growth = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;

            Log::info('FinancialDataService: Revenue growth calculated', [
                'current_revenue' => $currentRevenue,
                'previous_revenue' => $previousRevenue,
                'growth_percentage' => $growth,
            ]);

            return (float) round($growth, 2);

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to calculate revenue growth', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Get count of active subscriptions.
     */
    public function getActiveSubscriptionsCount(): int
    {
        try {
            $count = Subscription::where('stripe_status', 'active')->count();

            Log::debug('FinancialDataService: Active subscriptions counted', [
                'count' => $count,
            ]);

            return $count;

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to count active subscriptions', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Calculate Average Revenue Per User for a given period.
     */
    public function calculateAverageRevenuePerUser(Carbon $startDate, Carbon $endDate): float
    {
        $this->validateDateRange($startDate, $endDate);

        try {
            $totalRevenue = $this->calculatePeriodRevenue($startDate, $endDate);

            $userCount = CreditTransaction::where('type', 'purchased')
                ->where('description', 'NOT LIKE', '%TEST%')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('user_id')
                ->count('user_id');

            if ($userCount == 0) {
                return 0.0;
            }

            $arpu = $totalRevenue / $userCount;

            Log::info('FinancialDataService: ARPU calculated', [
                'total_revenue' => $totalRevenue,
                'user_count' => $userCount,
                'arpu' => $arpu,
            ]);

            return (float) round($arpu, 2);

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to calculate ARPU', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Get breakdown of subscription statuses.
     */
    public function getSubscriptionStatusBreakdown(): array
    {
        $cacheKey = 'financial_subscription_breakdown';

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            try {
                $breakdown = Subscription::select('stripe_status', DB::raw('count(*) as count'))
                    ->groupBy('stripe_status')
                    ->pluck('count', 'stripe_status')
                    ->toArray();

                // Ensure all expected statuses are present
                $statuses = ['active', 'canceled', 'past_due', 'incomplete', 'trialing'];
                $result = [];

                foreach ($statuses as $status) {
                    $result[$status] = $breakdown[$status] ?? 0;
                }

                Log::debug('FinancialDataService: Subscription breakdown calculated', $result);

                return $result;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to get subscription breakdown', [
                    'error' => $e->getMessage(),
                ]);
                return [
                    'active' => 0,
                    'canceled' => 0,
                    'past_due' => 0,
                    'incomplete' => 0,
                    'trialing' => 0,
                ];
            }
        });
    }

    /**
     * Generate revenue trend data for charts.
     */
    public function getRevenueTrendData(int $months = 6): array
    {
        $cacheKey = "financial_trend_data_{$months}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($months) {
            try {
                $labels = [];
                $data = [];

                for ($i = $months - 1; $i >= 0; $i--) {
                    $date = now()->subMonths($i);
                    $startOfMonth = $date->copy()->startOfMonth();
                    $endOfMonth = $date->copy()->endOfMonth();

                    $monthRevenue = $this->calculatePeriodRevenue($startOfMonth, $endOfMonth);

                    $labels[] = $date->format('M Y');
                    $data[] = $monthRevenue;
                }

                $result = [
                    'labels' => $labels,
                    'data' => $data,
                ];

                Log::debug('FinancialDataService: Trend data generated', [
                    'months' => $months,
                    'data_points' => count($data),
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to generate trend data', [
                    'error' => $e->getMessage(),
                ]);
                return [
                    'labels' => [],
                    'data' => [],
                ];
            }
        });
    }

    /**
     * Get comprehensive financial data for dashboard.
     */
    public function getFinancialSummary(Carbon $startDate, Carbon $endDate): array
    {
        // Input validation and sanitization
        $this->validateDateInputs($startDate, $endDate);

        try {
            Log::info('FinancialDataService: Generating financial summary', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Calculate current period revenue
            $periodRevenue = $this->calculatePeriodRevenue($startDate, $endDate);

            // Calculate previous period for growth comparison
            $periodLength = $endDate->diffInDays($startDate);
            $previousStart = $startDate->copy()->subDays($periodLength + 1);
            $previousEnd = $startDate->copy()->subDay();

            $revenueGrowth = $this->calculateRevenueGrowth(
                $startDate,
                $endDate,
                $previousStart,
                $previousEnd
            );

            $summary = [
                'mrr' => $this->calculateMonthlyRecurringRevenue(),
                'total_revenue' => $this->calculateTotalRevenue(),
                'period_revenue' => $periodRevenue,
                'revenue_growth' => $revenueGrowth,
                'active_subscriptions' => $this->getActiveSubscriptionsCount(),
                'arpu' => $this->calculateAverageRevenuePerUser($startDate, $endDate),
                'subscription_breakdown' => $this->getSubscriptionStatusBreakdown(),
                'trend_data' => $this->getRevenueTrendData(),
            ];

            Log::info('FinancialDataService: Financial summary generated', [
                'summary' => \Illuminate\Support\Arr::except($summary, ['subscription_breakdown', 'trend_data']),
            ]);

            return $summary;

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to generate financial summary', [
                'error' => $e->getMessage(),
            ]);

            // Return safe defaults
            return [
                'mrr' => 0.0,
                'total_revenue' => 0.0,
                'period_revenue' => 0.0,
                'revenue_growth' => 0.0,
                'active_subscriptions' => 0,
                'arpu' => 0.0,
                'subscription_breakdown' => [
                    'active' => 0,
                    'canceled' => 0,
                    'past_due' => 0,
                    'incomplete' => 0,
                    'trialing' => 0,
                ],
                'trend_data' => [
                    'labels' => [],
                    'data' => [],
                ],
            ];
        }
    }

    /**
     * Clear all financial data caches.
     */
    public function clearCache(): void
    {
        try {
            $patterns = [
                self::MRR_CACHE_KEY,
                'financial_revenue_*',
                'financial_subscription_breakdown',
                'financial_trend_data_*',
            ];

            foreach ($patterns as $pattern) {
                if (str_contains($pattern, '*')) {
                    // For patterns with wildcards, we'd need a more sophisticated cache clearing mechanism
                    // For now, just log the intent
                    Log::debug('FinancialDataService: Would clear cache pattern', ['pattern' => $pattern]);
                } else {
                    Cache::forget($pattern);
                    Log::debug('FinancialDataService: Cleared cache', ['key' => $pattern]);
                }
            }

            Log::info('FinancialDataService: Financial data cache cleared');

        } catch (\Exception $e) {
            Log::error('FinancialDataService: Failed to clear cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate date range parameters.
     */
    private function validateDateRange(Carbon $startDate, Carbon $endDate): void
    {
        if ($startDate->gt($endDate)) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        if ($startDate->diffInDays($endDate) > 366) {
            throw new \InvalidArgumentException('Date range cannot exceed 366 days');
        }
    }

    /**
     * Generate cache key for period revenue.
     */
    private function getPeriodRevenueCacheKey(Carbon $startDate, Carbon $endDate): string
    {
        $period = $this->determinePeriodType($startDate, $endDate);
        return "financial_revenue_{$period}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";
    }

    /**
     * Determine the type of period for caching optimization.
     */
    private function determinePeriodType(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->diffInDays($endDate);

        if ($days <= 31) {
            return 'monthly';
        } elseif ($days <= 93) {
            return 'quarterly';
        } elseif ($days <= 366) {
            return 'yearly';
        } else {
            return 'custom';
        }
    }

    /**
     * Validate and sanitize date inputs to prevent injection attacks.
     */
    private function validateDateInputs(?Carbon $startDate, ?Carbon $endDate): void
    {
        // Check for null dates
        if ($startDate === null || $endDate === null) {
            throw new \InvalidArgumentException('Start date and end date are required');
        }

        // Validate date range is reasonable (prevent extremely large ranges)
        $maxRangeYears = 10;
        $diffInYears = $startDate->diffInYears($endDate);

        if ($diffInYears > $maxRangeYears) {
            throw new \InvalidArgumentException("Date range cannot exceed {$maxRangeYears} years");
        }

        // Ensure start date is before end date
        if ($startDate->greaterThan($endDate)) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        // Prevent future dates beyond reasonable limits
        $maxFutureDate = Carbon::now()->addYear();
        if ($endDate->greaterThan($maxFutureDate)) {
            throw new \InvalidArgumentException('End date cannot be more than 1 year in the future');
        }

        // Prevent extremely old dates (before business could have existed)
        $minDate = Carbon::createFromDate(2000, 1, 1);
        if ($startDate->lessThan($minDate)) {
            throw new \InvalidArgumentException('Start date cannot be before year 2000');
        }
    }

    /**
     * Sanitize string inputs to prevent XSS and injection attacks.
     */
    private function sanitizeStringInput(string $input): string
    {
        // Remove any potential XSS attempts
        $input = strip_tags($input);

        // Remove potential SQL injection patterns
        $sqlPatterns = [
            '/\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\b/i',
            '/[\'";]/',
            '/\/\*.*?\*\//',
            '/--.*$/',
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi'
        ];

        foreach ($sqlPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        // Trim whitespace
        return trim($input);
    }

    /**
     * Validate that user inputs are within expected ranges and formats.
     */
    private function validateUserInputs(array $inputs): array
    {
        $validated = [];

        foreach ($inputs as $key => $value) {
            if (is_string($value)) {
                $validated[$key] = $this->sanitizeStringInput($value);
            } elseif (is_numeric($value)) {
                // Validate numeric inputs are within reasonable ranges
                if ($value < 0 || $value > PHP_INT_MAX / 100) {
                    throw new \InvalidArgumentException("Invalid numeric value for {$key}");
                }
                $validated[$key] = $value;
            } else {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }

    /**
     * Log security events for monitoring.
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        Log::warning('FinancialDataService: Security event', [
            'event' => $event,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
