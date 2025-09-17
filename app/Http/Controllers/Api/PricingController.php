<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PricingController extends Controller
{
    /**
     * Get all active subscription plans
     */
    public function index(): JsonResponse
    {
        $plans = Cache::remember('api_subscription_plans_active', 3600, function () {
            return SubscriptionPlan::active()
                ->ordered()
                ->get()
                ->map->toApiArray();
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'meta' => [
                'total' => $plans->count(),
                'cached_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get a specific subscription plan by slug
     */
    public function show(string $slug): JsonResponse
    {
        $plan = Cache::remember("api_subscription_plan_{$slug}", 3600, function () use ($slug) {
            return SubscriptionPlan::active()
                ->where('slug', $slug)
                ->first();
        });

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found or inactive',
                'error' => 'PLAN_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan->toApiArray(),
        ]);
    }

    /**
     * Get pricing comparison data
     */
    public function comparison(): JsonResponse
    {
        $plans = Cache::remember('api_pricing_comparison', 3600, function () {
            return SubscriptionPlan::active()
                ->ordered()
                ->get()
                ->map(function ($plan) {
                    $data = $plan->toApiArray();

                    // Add comparison-specific data
                    $data['best_value'] = $this->calculateBestValue($plan);
                    $data['popular'] = $plan->slug === 'professional'; // Mark professional as popular
                    $data['recommended'] = $plan->slug === 'professional';

                    return $data;
                });
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'meta' => [
                'comparison_mode' => true,
                'best_value_plan' => $plans->where('best_value', true)->first()['slug'] ?? null,
                'popular_plan' => $plans->where('popular', true)->first()['slug'] ?? null,
            ]
        ]);
    }

    /**
     * Get pricing for a specific frequency
     */
    public function frequency(string $frequency): JsonResponse
    {
        $validFrequencies = ['weekly', 'monthly', 'yearly'];

        if (!in_array($frequency, $validFrequencies)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid frequency',
                'error' => 'INVALID_FREQUENCY',
                'valid_frequencies' => $validFrequencies
            ], 400);
        }

        $plans = Cache::remember("api_pricing_frequency_{$frequency}", 3600, function () use ($frequency) {
            return SubscriptionPlan::active()
                ->where("is_{$frequency}_enabled", true)
                ->whereNotNull("{$frequency}_price")
                ->ordered()
                ->get()
                ->map(function ($plan) use ($frequency) {
                    $apiData = $plan->toApiArray();

                    // Only return the specific frequency data
                    return [
                        'id' => $apiData['id'],
                        'name' => $apiData['name'],
                        'slug' => $apiData['slug'],
                        'description' => $apiData['description'],
                        'features' => $apiData['features'],
                        'limits' => $apiData['limits'],
                        'permissions' => $apiData['permissions'],
                        'pricing' => $apiData['pricing'][$frequency],
                        'frequency' => $frequency,
                        'is_active' => $apiData['is_active'],
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'meta' => [
                'frequency' => $frequency,
                'total' => $plans->count(),
            ]
        ]);
    }

    /**
     * Get feature matrix for all plans
     */
    public function features(): JsonResponse
    {
        $plans = Cache::remember('api_plans_feature_matrix', 3600, function () {
            return SubscriptionPlan::active()
                ->ordered()
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'features' => $plan->features ?? [],
                        'limits' => [
                            'max_alerts_per_month' => $plan->max_alerts_per_month,
                            'max_courses' => $plan->max_courses,
                        ],
                        'permissions' => [
                            'premium_chat_access' => $plan->premium_chat_access,
                            'premium_events_access' => $plan->premium_events_access,
                            'advanced_analytics' => $plan->advanced_analytics,
                            'priority_support' => $plan->priority_support,
                        ],
                    ];
                });
        });

        // Extract all unique features across plans
        $allFeatures = $plans->pluck('features')->flatten()->unique()->values();

        // Extract all permission types
        $allPermissions = [
            'premium_chat_access' => 'Premium Chat Access',
            'premium_events_access' => 'Premium Events Access',
            'advanced_analytics' => 'Advanced Analytics',
            'priority_support' => 'Priority Support',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans,
                'feature_matrix' => [
                    'features' => $allFeatures,
                    'permissions' => $allPermissions,
                ],
            ],
        ]);
    }

    /**
     * Get plan statistics
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('api_pricing_stats', 1800, function () {
            $activePlans = SubscriptionPlan::active()->get();

            return [
                'total_plans' => SubscriptionPlan::count(),
                'active_plans' => $activePlans->count(),
                'inactive_plans' => SubscriptionPlan::where('is_active', false)->count(),
                'frequency_support' => [
                    'weekly' => $activePlans->where('is_weekly_enabled', true)->count(),
                    'monthly' => $activePlans->where('is_monthly_enabled', true)->count(),
                    'yearly' => $activePlans->where('is_yearly_enabled', true)->count(),
                ],
                'price_range' => [
                    'monthly' => [
                        'min' => $activePlans->whereNotNull('monthly_price')->min('monthly_price'),
                        'max' => $activePlans->whereNotNull('monthly_price')->max('monthly_price'),
                        'avg' => $activePlans->whereNotNull('monthly_price')->avg('monthly_price'),
                    ],
                    'yearly' => [
                        'min' => $activePlans->whereNotNull('yearly_price')->min('yearly_price'),
                        'max' => $activePlans->whereNotNull('yearly_price')->max('yearly_price'),
                        'avg' => $activePlans->whereNotNull('yearly_price')->avg('yearly_price'),
                    ],
                ],
                'features_count' => $activePlans->sum(function ($plan) {
                    return count($plan->features ?? []);
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Search plans by features or description
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $frequency = $request->input('frequency', 'monthly');

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required',
                'error' => 'MISSING_QUERY'
            ], 400);
        }

        $plans = SubscriptionPlan::active()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhereJsonContains('features', $query);
            })
            ->when($frequency !== 'all', function ($q) use ($frequency) {
                $q->where("is_{$frequency}_enabled", true)
                  ->whereNotNull("{$frequency}_price");
            })
            ->ordered()
            ->get()
            ->map->toApiArray();

        return response()->json([
            'success' => true,
            'data' => $plans,
            'meta' => [
                'query' => $query,
                'frequency' => $frequency,
                'total_results' => $plans->count(),
            ]
        ]);
    }

    /**
     * Calculate if a plan offers the best value (highest discount on yearly)
     */
    private function calculateBestValue(SubscriptionPlan $plan): bool
    {
        if (!$plan->is_yearly_enabled || !$plan->yearly_discount_percentage) {
            return false;
        }

        // Consider plans with 15%+ yearly discount as best value
        return $plan->yearly_discount_percentage >= 15;
    }
}
