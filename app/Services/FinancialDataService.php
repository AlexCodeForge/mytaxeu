<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditTransaction;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FinancialDataService
{

    /**
     * Calculate Monthly Recurring Revenue from active subscriptions.
     *
     * This method calculates MRR by aggregating revenue from all active Stripe subscriptions.
     * Gets actual pricing from Stripe API for accurate calculations.
     * Optimized with caching and batch Stripe API calls.
     */
    public function calculateMonthlyRecurringRevenue(): float
    {
        $cacheKey = 'mrr_calculation_' . now()->startOfHour()->format('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () { // Cache for 1 hour
            try {
                Log::debug('FinancialDataService: Calculating MRR from active subscriptions');

                // Get active subscriptions with their items in one query
                $activeSubscriptions = Subscription::where('stripe_status', 'active')
                    ->with('items')
                    ->get();

                if ($activeSubscriptions->isEmpty()) {
                    return 0.0;
                }

                $totalMrr = 0.0;
                $stripeConfig = \App\Models\AdminSetting::getStripeConfig();
                \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

                // Batch Stripe API calls to reduce round trips
                $stripeIds = $activeSubscriptions->pluck('stripe_id')->toArray();
                $stripeSubscriptions = [];

                // Retrieve multiple subscriptions in one batch
                try {
                    $retrievedSubscriptions = \Stripe\Subscription::all([
                        'ids' => $stripeIds,
                        'expand' => ['data.items.data.price']
                    ]);

                    foreach ($retrievedSubscriptions->data as $stripeSub) {
                        $stripeSubscriptions[$stripeSub->id] = $stripeSub;
                    }
                } catch (\Exception $e) {
                    Log::warning('Batch Stripe retrieval failed, falling back to individual calls', [
                        'error' => $e->getMessage()
                    ]);

                    // Fallback: individual calls if batch fails
                    foreach ($activeSubscriptions as $subscription) {
                        try {
                            $stripeSubscriptions[$subscription->stripe_id] =
                                \Stripe\Subscription::retrieve($subscription->stripe_id);
                        } catch (\Exception $e) {
                            Log::warning('Failed to retrieve subscription from Stripe for MRR calculation', [
                                'subscription_id' => $subscription->stripe_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Calculate MRR from retrieved subscriptions
                foreach ($activeSubscriptions as $subscription) {
                    $stripeSub = $stripeSubscriptions[$subscription->stripe_id] ?? null;

                    if (!$stripeSub || $stripeSub->status !== 'active') {
                        continue;
                    }

                    foreach ($stripeSub->items->data as $item) {
                        $priceAmount = $item->price->unit_amount ?? 0;
                        $quantity = $item->quantity ?? 1;
                        $monthlyAmount = $this->convertToMonthlyAmount($priceAmount,
                            $item->price->recurring->interval ?? 'month');
                        $totalMrr += ($monthlyAmount * $quantity) / 100;
                    }
                }

                Log::info('FinancialDataService: MRR calculated efficiently', [
                    'active_subscriptions' => $activeSubscriptions->count(),
                    'mrr_eur' => $totalMrr,
                ]);

                return (float) $totalMrr;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to calculate MRR', [
                    'error' => $e->getMessage(),
                ]);
                return 0.0;
            }
        });
    }

    /**
     * Convert price amount to monthly equivalent
     */
    private function convertToMonthlyAmount(int $amount, string $interval): int
    {
        switch ($interval) {
            case 'day':
                return $amount * 30; // Approximate monthly
            case 'week':
                return $amount * 4; // Approximate monthly
            case 'month':
                return $amount;
            case 'year':
                return (int) ($amount / 12);
            default:
                return $amount;
        }
    }

    /**
     * Calculate total revenue from all subscription payments (optimized with caching).
     */
    public function calculateTotalRevenue(): float
    {
        $cacheKey = 'total_revenue_calculation';

        return Cache::remember($cacheKey, 3600, function () { // Cache for 1 hour
            try {
                Log::debug('FinancialDataService: Calculating total revenue');

                $totalRevenue = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->where('description', 'LIKE', '%Pago de suscripción%') // Only actual payment transactions
                    ->sum('amount');

                $revenueInEuros = $totalRevenue / 100; // Convert cents to euros

                Log::info('FinancialDataService: Total revenue calculated', [
                    'total_revenue_cents' => $totalRevenue,
                    'total_revenue_euros' => $revenueInEuros,
                ]);

                return (float) $revenueInEuros;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to calculate total revenue', [
                    'error' => $e->getMessage(),
                ]);
                return 0.0;
            }
        });
    }

    /**
     * Calculate revenue for a specific time period (optimized with caching).
     */
    public function calculatePeriodRevenue(Carbon $startDate, Carbon $endDate): float
    {
        $this->validateDateRange($startDate, $endDate);

        $cacheKey = "period_revenue_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            try {
                Log::debug('FinancialDataService: Calculating period revenue', [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]);

                $totalRevenue = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->where('description', 'LIKE', '%Pago de suscripción%') // Only actual payment transactions
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');

                $revenueInEuros = $totalRevenue / 100;

                Log::info('FinancialDataService: Period revenue calculated', [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'revenue' => $revenueInEuros,
                ]);

                return (float) $revenueInEuros;

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
     * Calculate Average Revenue Per User for a given period (optimized).
     */
    public function calculateAverageRevenuePerUser(Carbon $startDate, Carbon $endDate): float
    {
        $this->validateDateRange($startDate, $endDate);

        $cacheKey = "arpu_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            try {
                // Optimized: Get both total revenue and user count in one query
                $result = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->where('description', 'LIKE', '%Pago de suscripción%') // Only actual payment transactions
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('SUM(amount) as total_revenue, COUNT(DISTINCT user_id) as user_count')
                    ->first();

                $totalRevenue = $result->total_revenue ?? 0;
                $userCount = $result->user_count ?? 0;

                if ($userCount == 0) {
                    return 0.0;
                }

                $arpu = ($totalRevenue / 100) / $userCount; // Convert cents to euros and divide by user count

                Log::info('FinancialDataService: ARPU calculated', [
                    'total_revenue' => $totalRevenue / 100,
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
        });
    }

    /**
     * Get breakdown of subscription statuses.
     */
    public function getSubscriptionStatusBreakdown(): array
    {
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
    }

    /**
     * Generate revenue trend data for charts (optimized with batch queries).
     * Now supports custom date ranges for proper filtering.
     */
    public function getRevenueTrendData(?Carbon $startDate = null, ?Carbon $endDate = null, int $months = 6): array
    {
        // If no custom date range provided, use default behavior (last N months)
        if ($startDate === null || $endDate === null) {
            return $this->getDefaultRevenueTrendData($months);
        }

        $this->validateDateRange($startDate, $endDate);

        $cacheKey = "revenue_trend_data_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            try {
                $labels = [];
                $data = [];

                // Determine the appropriate interval based on date range
                $daysDiff = $startDate->diffInDays($endDate);

                if ($daysDiff <= 31) {
                    // Daily data for periods up to 1 month
                    $dateRanges = $this->generateDailyRanges($startDate, $endDate);
                } elseif ($daysDiff <= 93) {
                    // Weekly data for periods up to 3 months
                    $dateRanges = $this->generateWeeklyRanges($startDate, $endDate);
                } else {
                    // Monthly data for longer periods
                    $dateRanges = $this->generateMonthlyRanges($startDate, $endDate);
                }

                // Get all transactions in the date range
                $transactions = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->where('description', 'LIKE', '%Pago de suscripción%')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->select('amount', 'created_at')
                    ->get();

                // Group transactions by period in PHP (database-agnostic)
                $revenueResults = [];
                foreach ($transactions as $transaction) {
                    $date = Carbon::parse($transaction->created_at);

                    if ($daysDiff <= 31) {
                        // Daily grouping
                        $key = $date->format('Y-m-d');
                    } elseif ($daysDiff <= 93) {
                        // Weekly grouping
                        $key = $date->format('Y-W');
                    } else {
                        // Monthly grouping
                        $key = $date->format('Y-m-01');
                    }

                    if (!isset($revenueResults[$key])) {
                        $revenueResults[$key] = 0;
                    }
                    $revenueResults[$key] += $transaction->amount;
                }

                // Map results to date ranges
                foreach ($dateRanges as $range) {
                    $revenue = $revenueResults[$range['key']] ?? 0;
                    $labels[] = $range['label'];
                    $data[] = $revenue / 100; // Convert cents to euros
                }

                $result = [
                    'labels' => $labels,
                    'data' => $data,
                ];

                Log::debug('FinancialDataService: Custom trend data generated', [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'data_points' => count($data),
                    'days_diff' => $daysDiff,
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to generate custom trend data', [
                    'error' => $e->getMessage(),
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]);
                return [
                    'labels' => [],
                    'data' => [],
                ];
            }
        });
    }

    /**
     * Generate default revenue trend data (backwards compatibility).
     */
    private function getDefaultRevenueTrendData(int $months): array
    {
        $cacheKey = "revenue_trend_data_{$months}_months";

        return Cache::remember($cacheKey, 3600, function () use ($months) {
            try {
                $labels = [];
                $data = [];

                // Generate date ranges for all months
                $dateRanges = [];
                for ($i = $months - 1; $i >= 0; $i--) {
                    $date = now()->subMonths($i);
                    $startOfMonth = $date->copy()->startOfMonth();
                    $endOfMonth = $date->copy()->endOfMonth();

                    $dateRanges[] = [
                        'start' => $startOfMonth,
                        'end' => $endOfMonth,
                        'label' => $date->format('M Y')
                    ];
                }

                // Get all transactions in the date range (database-agnostic)
                $transactions = CreditTransaction::where('type', 'purchased')
                    ->where('description', 'NOT LIKE', '%TEST%')
                    ->where('description', 'LIKE', '%Pago de suscripción%')
                    ->whereBetween('created_at', [
                        $dateRanges[0]['start'], // Earliest start date
                        $dateRanges[count($dateRanges) - 1]['end'] // Latest end date
                    ])
                    ->select('amount', 'created_at')
                    ->get();

                // Group by month in PHP (database-agnostic)
                $revenueResults = [];
                foreach ($transactions as $transaction) {
                    $monthKey = Carbon::parse($transaction->created_at)->format('Y-m-01');
                    if (!isset($revenueResults[$monthKey])) {
                        $revenueResults[$monthKey] = 0;
                    }
                    $revenueResults[$monthKey] += $transaction->amount;
                }

                // Map results to date ranges
                foreach ($dateRanges as $range) {
                    $monthKey = $range['start']->format('Y-m-01');
                    $revenue = $revenueResults[$monthKey] ?? 0;
                    $labels[] = $range['label'];
                    $data[] = $revenue / 100; // Convert cents to euros
                }

                $result = [
                    'labels' => $labels,
                    'data' => $data,
                ];

                Log::debug('FinancialDataService: Default trend data generated', [
                    'months' => $months,
                    'data_points' => count($data),
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('FinancialDataService: Failed to generate default trend data', [
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
     * Generate daily date ranges for short periods.
     */
    private function generateDailyRanges(Carbon $startDate, Carbon $endDate): array
    {
        $ranges = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $ranges[] = [
                'key' => $current->format('Y-m-d'),
                'label' => $current->format('M j')
            ];
            $current->addDay();
        }

        return $ranges;
    }

    /**
     * Generate weekly date ranges for medium periods.
     */
    private function generateWeeklyRanges(Carbon $startDate, Carbon $endDate): array
    {
        $ranges = [];
        $current = $startDate->copy()->startOfWeek();

        while ($current->lte($endDate)) {
            $weekEnd = $current->copy()->endOfWeek();
            if ($weekEnd->gt($endDate)) {
                $weekEnd = $endDate->copy();
            }

            $ranges[] = [
                'key' => $current->format('Y-W'), // Year-Week format
                'label' => $current->format('M j') . '-' . $weekEnd->format('j')
            ];
            $current->addWeek();
        }

        return $ranges;
    }

    /**
     * Generate monthly date ranges for long periods.
     */
    private function generateMonthlyRanges(Carbon $startDate, Carbon $endDate): array
    {
        $ranges = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $ranges[] = [
                'key' => $current->format('Y-m-01'),
                'label' => $current->format('M Y')
            ];
            $current->addMonth();
        }

        return $ranges;
    }

    /**
     * Get comprehensive financial data for dashboard (optimized with caching).
     */
    public function getFinancialSummary(Carbon $startDate, Carbon $endDate): array
    {
        // Input validation and sanitization
        $this->validateDateInputs($startDate, $endDate);

        $cacheKey = "financial_summary_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 900, function () use ($startDate, $endDate) { // Cache for 15 minutes
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
                    'trend_data' => $this->getRevenueTrendData($startDate, $endDate),
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
        });
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
