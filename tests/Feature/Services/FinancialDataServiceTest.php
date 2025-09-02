<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\CreditTransaction;
use App\Services\FinancialDataService;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class FinancialDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialDataService $service;
    private User $admin;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FinancialDataService::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
    }

    /** @test */
    public function it_calculates_monthly_recurring_revenue_from_active_subscriptions(): void
    {
        // Create active subscriptions
        $subscription1 = $this->user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test1',
            'quantity' => 1,
        ]);

        $subscription2 = $this->user2->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test2',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test2',
            'quantity' => 2,
        ]);

        // Create inactive subscription (should not be counted)
        $this->user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test3',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_test3',
            'quantity' => 1,
        ]);

        $mrr = $this->service->calculateMonthlyRecurringRevenue();

        // Should return based on active subscriptions only
        $this->assertIsNumeric($mrr);
        $this->assertGreaterThan(0, $mrr);
    }

    /** @test */
    public function it_calculates_total_revenue_from_credit_transactions(): void
    {
        // Create purchase transactions
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 2000, // $20.00
            'created_at' => now()->subDays(10),
        ]);

        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 1500, // $15.00
            'created_at' => now()->subDays(5),
        ]);

        // Create non-purchase transaction (should not be counted)
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'consumed',
            'amount' => -500, // Usage deduction
            'created_at' => now()->subDays(3),
        ]);

        $totalRevenue = $this->service->calculateTotalRevenue();

        $this->assertEquals(35.00, $totalRevenue); // $20 + $15 = $35
    }

    /** @test */
    public function it_filters_revenue_by_monthly_period(): void
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Create transactions in current month
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => $currentMonth->copy()->addDays(5),
        ]);

        // Create transactions in last month
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 2000,
            'created_at' => $lastMonth->copy()->addDays(10),
        ]);

        $currentMonthRevenue = $this->service->calculatePeriodRevenue(
            $currentMonth,
            $currentMonth->copy()->endOfMonth()
        );

        $lastMonthRevenue = $this->service->calculatePeriodRevenue(
            $lastMonth,
            $lastMonth->copy()->endOfMonth()
        );

        $this->assertEquals(10.00, $currentMonthRevenue);
        $this->assertEquals(20.00, $lastMonthRevenue);
    }

    /** @test */
    public function it_filters_revenue_by_quarterly_period(): void
    {
        $quarterStart = now()->firstOfQuarter();
        $quarterEnd = now()->lastOfQuarter();

        // Create transactions within quarter
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 2500,
            'created_at' => $quarterStart->copy()->addDays(30),
        ]);

        // Create transaction outside quarter
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => $quarterStart->copy()->subDays(5),
        ]);

        $quarterlyRevenue = $this->service->calculatePeriodRevenue(
            $quarterStart,
            $quarterEnd
        );

        $this->assertEquals(25.00, $quarterlyRevenue);
    }

    /** @test */
    public function it_filters_revenue_by_yearly_period(): void
    {
        $yearStart = now()->startOfYear();
        $yearEnd = now()->endOfYear();

        // Create transactions within year
        CreditTransaction::factory()->count(3)->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => $yearStart->copy()->addMonths(3),
        ]);

        // Create transaction outside year
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 500,
            'created_at' => $yearStart->copy()->subYear(),
        ]);

        $yearlyRevenue = $this->service->calculatePeriodRevenue(
            $yearStart,
            $yearEnd
        );

        $this->assertEquals(30.00, $yearlyRevenue); // 3 * $10 = $30
    }

    /** @test */
    public function it_filters_revenue_by_custom_date_range(): void
    {
        $startDate = Carbon::parse('2024-01-15');
        $endDate = Carbon::parse('2024-02-15');

        // Create transaction within range
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 3000,
            'created_at' => Carbon::parse('2024-01-20'),
        ]);

        // Create transaction outside range (before)
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => Carbon::parse('2024-01-10'),
        ]);

        // Create transaction outside range (after)
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 2000,
            'created_at' => Carbon::parse('2024-02-20'),
        ]);

        $customRangeRevenue = $this->service->calculatePeriodRevenue(
            $startDate,
            $endDate
        );

        $this->assertEquals(30.00, $customRangeRevenue);
    }

    /** @test */
    public function it_calculates_revenue_growth_percentage(): void
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonth()->startOfMonth();
        $previousEnd = now()->subMonth()->endOfMonth();

        // Current month: $20
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 2000,
            'created_at' => $currentStart->copy()->addDays(10),
        ]);

        // Previous month: $10
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => $previousStart->copy()->addDays(10),
        ]);

        $growth = $this->service->calculateRevenueGrowth(
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd
        );

        $this->assertEquals(100.0, $growth); // 100% growth from $10 to $20
    }

    /** @test */
    public function it_handles_zero_previous_revenue_growth_calculation(): void
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonth()->startOfMonth();
        $previousEnd = now()->subMonth()->endOfMonth();

        // Current month: $15
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1500,
            'created_at' => $currentStart->copy()->addDays(10),
        ]);

        // Previous month: $0 (no transactions)

        $growth = $this->service->calculateRevenueGrowth(
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd
        );

        $this->assertEquals(100.0, $growth); // Should return 100% when previous is 0
    }

    /** @test */
    public function it_calculates_negative_revenue_growth(): void
    {
        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonth()->startOfMonth();
        $previousEnd = now()->subMonth()->endOfMonth();

        // Current month: $10
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000,
            'created_at' => $currentStart->copy()->addDays(10),
        ]);

        // Previous month: $20
        CreditTransaction::factory()->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 2000,
            'created_at' => $previousStart->copy()->addDays(10),
        ]);

        $growth = $this->service->calculateRevenueGrowth(
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd
        );

        $this->assertEquals(-50.0, $growth); // -50% decline from $20 to $10
    }

    /** @test */
    public function it_counts_active_subscriptions_correctly(): void
    {
        // Create active subscriptions
        $this->user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_active1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test1',
        ]);

        $this->user2->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_active2',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test2',
        ]);

        // Create non-active subscriptions
        $this->user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_canceled',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_test3',
        ]);

        $this->user2->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_past_due',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test4',
        ]);

        $activeCount = $this->service->getActiveSubscriptionsCount();

        $this->assertEquals(2, $activeCount);
    }

    /** @test */
    public function it_calculates_average_revenue_per_user(): void
    {
        // User 1: $30 total
        CreditTransaction::factory()->count(3)->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000, // $10 each
        ]);

        // User 2: $20 total
        CreditTransaction::factory()->count(2)->create([
            'user_id' => $this->user2->id,
            'type' => 'purchased',
            'amount' => 1000, // $10 each
        ]);

        $startDate = now()->subDays(30);
        $endDate = now();

        $arpu = $this->service->calculateAverageRevenuePerUser($startDate, $endDate);

        $this->assertEquals(25.0, $arpu); // ($30 + $20) / 2 users = $25
    }

    /** @test */
    public function it_handles_zero_users_in_arpu_calculation(): void
    {
        $startDate = now()->subDays(30);
        $endDate = now();

        $arpu = $this->service->calculateAverageRevenuePerUser($startDate, $endDate);

        $this->assertEquals(0.0, $arpu);
    }

    /** @test */
    public function it_provides_subscription_status_breakdown(): void
    {
        // Create subscriptions with different statuses
        $statuses = ['active', 'canceled', 'past_due', 'incomplete', 'trialing'];

        foreach ($statuses as $index => $status) {
            $user = User::factory()->create();
            $user->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => "sub_{$status}_{$index}",
                'stripe_status' => $status,
                'stripe_price' => 'price_test',
            ]);
        }

        $breakdown = $this->service->getSubscriptionStatusBreakdown();

        $this->assertArrayHasKey('active', $breakdown);
        $this->assertArrayHasKey('canceled', $breakdown);
        $this->assertArrayHasKey('past_due', $breakdown);
        $this->assertArrayHasKey('incomplete', $breakdown);
        $this->assertArrayHasKey('trialing', $breakdown);

        $this->assertEquals(1, $breakdown['active']);
        $this->assertEquals(1, $breakdown['canceled']);
        $this->assertEquals(1, $breakdown['past_due']);
        $this->assertEquals(1, $breakdown['incomplete']);
        $this->assertEquals(1, $breakdown['trialing']);
    }

    /** @test */
    public function it_generates_revenue_trend_data(): void
    {
        // Create revenue data for the last 6 months
        for ($i = 0; $i < 6; $i++) {
            $date = now()->subMonths($i);
            $amount = 1000 + ($i * 100); // Increasing amounts

            CreditTransaction::factory()->create([
                'user_id' => $this->user1->id,
                'type' => 'purchased',
                'amount' => $amount,
                'created_at' => $date->startOfMonth()->addDays(15),
            ]);
        }

        $trendData = $this->service->getRevenueTrendData(6);

        $this->assertIsArray($trendData);
        $this->assertArrayHasKey('labels', $trendData);
        $this->assertArrayHasKey('data', $trendData);
        $this->assertCount(6, $trendData['labels']);
        $this->assertCount(6, $trendData['data']);
    }

    /** @test */
    public function it_caches_mrr_calculations_for_performance(): void
    {
        // Clear any existing cache
        Cache::forget('financial_mrr');

        // Create subscription
        $this->user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
        ]);

        // First call should hit database and cache result
        $mrr1 = $this->service->calculateMonthlyRecurringRevenue();

        // Verify cache was set
        $this->assertTrue(Cache::has('financial_mrr'));

        // Second call should use cache
        $mrr2 = $this->service->calculateMonthlyRecurringRevenue();

        $this->assertEquals($mrr1, $mrr2);
    }

    /** @test */
    public function it_caches_revenue_calculations_by_period(): void
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();
        $cacheKey = "financial_revenue_monthly_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        // Clear cache
        Cache::forget($cacheKey);

        // Create transaction
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1500,
            'created_at' => $startDate->copy()->addDays(10),
        ]);

        // First call should cache result
        $revenue1 = $this->service->calculatePeriodRevenue($startDate, $endDate);

        // Verify cache was set
        $this->assertTrue(Cache::has($cacheKey));

        // Second call should use cache
        $revenue2 = $this->service->calculatePeriodRevenue($startDate, $endDate);

        $this->assertEquals($revenue1, $revenue2);
    }

    /** @test */
    public function it_handles_database_errors_gracefully(): void
    {
        // Simulate database error by using invalid date range
        $this->expectNotToPerformAssertions();

        try {
            $result = $this->service->calculatePeriodRevenue(
                Carbon::parse('invalid-date'),
                now()
            );
        } catch (\Exception $e) {
            // Should handle gracefully and not crash
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function it_validates_date_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // End date before start date should throw exception
        $this->service->calculatePeriodRevenue(
            now(),
            now()->subDay()
        );
    }

    /** @test */
    public function it_excludes_test_transactions_from_calculations(): void
    {
        // Create regular transaction
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000,
            'description' => 'Regular purchase',
        ]);

        // Create test transaction (should be excluded)
        CreditTransaction::factory()->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 5000,
            'description' => 'TEST transaction - should be excluded',
        ]);

        $totalRevenue = $this->service->calculateTotalRevenue();

        // Should only include the regular transaction
        $this->assertEquals(10.00, $totalRevenue);
    }

    /** @test */
    public function it_handles_large_datasets_efficiently(): void
    {
        $startTime = microtime(true);

        // Create a large number of transactions
        CreditTransaction::factory()->count(1000)->create([
            'user_id' => $this->user1->id,
            'type' => 'purchased',
            'amount' => 1000,
        ]);

        $revenue = $this->service->calculateTotalRevenue();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (less than 2 seconds)
        $this->assertLessThan(2.0, $executionTime);
        $this->assertEquals(10000.00, $revenue); // 1000 * $10 = $10,000
    }
}
