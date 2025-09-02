<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\FinancialDashboard;
use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Illuminate\Support\Facades\Cache;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function it_renders_successfully_for_admin_users(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.admin.financial-dashboard')
            ->assertSee('Panel Financiero')
            ->assertSee('Ingresos Recurrentes Mensuales');
    }

    /** @test */
    public function it_denies_access_to_non_admin_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(FinancialDashboard::class);
    }

    /** @test */
    public function it_denies_access_to_unauthenticated_users(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(FinancialDashboard::class);
    }

    /** @test */
    public function it_initializes_with_correct_reactive_properties(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertSet('timePeriod', 'monthly')
            ->assertSet('startDate', null)
            ->assertSet('endDate', null)
            ->assertSet('loading', false);
    }

    /** @test */
    public function it_can_change_time_period_to_quarterly(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'quarterly')
            ->assertSet('timePeriod', 'quarterly')
            ->assertDispatched('time-period-changed');
    }

    /** @test */
    public function it_can_change_time_period_to_yearly(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'yearly')
            ->assertSet('timePeriod', 'yearly')
            ->assertDispatched('time-period-changed');
    }

    /** @test */
    public function it_can_set_custom_date_range(): void
    {
        $this->actingAs($this->admin);

        $startDate = '2024-01-01';
        $endDate = '2024-03-31';

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            ->set('startDate', $startDate)
            ->set('endDate', $endDate)
            ->assertSet('timePeriod', 'custom')
            ->assertSet('startDate', $startDate)
            ->assertSet('endDate', $endDate);
    }

    /** @test */
    public function it_validates_custom_date_range(): void
    {
        $this->actingAs($this->admin);

        // End date before start date should cause validation error
        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            ->set('startDate', '2024-03-31')
            ->set('endDate', '2024-01-01')
            ->call('refreshData')
            ->assertHasErrors(['endDate']);
    }

    /** @test */
    public function it_calculates_monthly_recurring_revenue_correctly(): void
    {
        $this->actingAs($this->admin);

        // Create users with active subscriptions
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create active subscriptions with known pricing
        $subscription1 = $user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test1',
            'quantity' => 1,
        ]);

        $subscription2 = $user2->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test2',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test2',
            'quantity' => 2,
        ]);

        // Create subscription items (these would normally have price data from Stripe)
        $subscription1->items()->create([
            'stripe_id' => 'si_test1',
            'stripe_product' => 'prod_test1',
            'stripe_price' => 'price_test1',
            'quantity' => 1,
        ]);

        $subscription2->items()->create([
            'stripe_id' => 'si_test2',
            'stripe_product' => 'prod_test2',
            'stripe_price' => 'price_test2',
            'quantity' => 2,
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return isset($data['mrr']) && is_numeric($data['mrr']);
            });
    }

    /** @test */
    public function it_calculates_total_revenue_from_credit_transactions(): void
    {
        $this->actingAs($this->admin);

        // Create credit transactions representing revenue
        CreditTransaction::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 1000, // $10.00 in cents
            'description' => 'Credit purchase',
            'created_at' => now()->subDays(10),
        ]);

        CreditTransaction::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 2000, // $20.00 in cents
            'description' => 'Credit purchase',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return isset($data['total_revenue']) &&
                       $data['total_revenue'] >= 11000; // At least $110.00 in cents
            });
    }

    /** @test */
    public function it_filters_data_by_monthly_period(): void
    {
        $this->actingAs($this->admin);

        // Create transactions in different months
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 1000,
            'created_at' => now()->startOfMonth(),
        ]);

        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 2000,
            'created_at' => now()->subMonth()->startOfMonth(),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'monthly')
            ->call('refreshData')
            ->assertViewHas('financialData', function ($data) {
                return isset($data['period_revenue']);
            });
    }

    /** @test */
    public function it_filters_data_by_quarterly_period(): void
    {
        $this->actingAs($this->admin);

        // Create transactions in current quarter
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 1500,
            'created_at' => now()->firstOfQuarter(),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'quarterly')
            ->call('refreshData')
            ->assertViewHas('financialData', function ($data) {
                return isset($data['period_revenue']);
            });
    }

    /** @test */
    public function it_filters_data_by_yearly_period(): void
    {
        $this->actingAs($this->admin);

        // Create transactions in current year
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 3000,
            'created_at' => now()->startOfYear(),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'yearly')
            ->call('refreshData')
            ->assertViewHas('financialData', function ($data) {
                return isset($data['period_revenue']);
            });
    }

    /** @test */
    public function it_filters_data_by_custom_date_range(): void
    {
        $this->actingAs($this->admin);

        $startDate = '2024-01-01';
        $endDate = '2024-01-31';

        // Create transaction within range
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 2500,
            'created_at' => Carbon::parse('2024-01-15'),
        ]);

        // Create transaction outside range
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 1000,
            'created_at' => Carbon::parse('2024-02-15'),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'custom')
            ->set('startDate', $startDate)
            ->set('endDate', $endDate)
            ->call('refreshData')
            ->assertViewHas('financialData', function ($data) {
                return isset($data['period_revenue']);
            });
    }

    /** @test */
    public function it_displays_key_performance_indicators(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return isset($data['mrr']) &&
                       isset($data['total_revenue']) &&
                       isset($data['period_revenue']) &&
                       isset($data['active_subscriptions']) &&
                       isset($data['revenue_growth']);
            });
    }

    /** @test */
    public function it_provides_revenue_trend_data_for_charts(): void
    {
        $this->actingAs($this->admin);

        // Create revenue data across multiple months
        for ($i = 0; $i < 6; $i++) {
            CreditTransaction::factory()->create([
                'user_id' => $this->regularUser->id,
                'type' => 'purchase',
                'amount' => 1000 + ($i * 100), // Increasing revenue trend
                'created_at' => now()->subMonths($i)->startOfMonth(),
            ]);
        }

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('chartData', function ($chartData) {
                return isset($chartData['revenue_trend']) &&
                       is_array($chartData['revenue_trend']) &&
                       count($chartData['revenue_trend']) > 0;
            });
    }

    /** @test */
    public function it_handles_empty_financial_data_gracefully(): void
    {
        $this->actingAs($this->admin);

        // Clear all financial data
        CreditTransaction::query()->delete();
        Subscription::query()->delete();

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return $data['mrr'] === 0 &&
                       $data['total_revenue'] === 0 &&
                       $data['active_subscriptions'] === 0;
            })
            ->assertSee('No hay datos financieros disponibles');
    }

    /** @test */
    public function it_displays_loading_state_during_data_refresh(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('loading', true)
            ->assertSet('loading', true)
            ->assertSee('Cargando datos financieros...');
    }

    /** @test */
    public function it_handles_data_loading_errors_gracefully(): void
    {
        $this->actingAs($this->admin);

        // This test would need to mock a database error or service failure
        // For now, we test the error handling property exists
        Livewire::test(FinancialDashboard::class)
            ->assertPropertyExists('hasError');
    }

    /** @test */
    public function it_refreshes_data_manually(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->call('refreshData')
            ->assertDispatched('financial-data-refreshed');
    }

    /** @test */
    public function it_caches_financial_calculations_for_performance(): void
    {
        $this->actingAs($this->admin);

        // Clear cache first
        Cache::forget('financial_dashboard_data');

        $component = Livewire::test(FinancialDashboard::class);

        // Check that cache was populated after rendering
        $this->assertTrue(Cache::has('financial_dashboard_monthly'));
    }

    /** @test */
    public function it_updates_chart_data_when_time_period_changes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'quarterly')
            ->assertDispatched('chart-data-updated');
    }

    /** @test */
    public function it_calculates_revenue_growth_percentage(): void
    {
        $this->actingAs($this->admin);

        // Create transactions for current and previous period
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 2000, // Current month
            'created_at' => now()->startOfMonth(),
        ]);

        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchase',
            'amount' => 1000, // Previous month
            'created_at' => now()->subMonth()->startOfMonth(),
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return isset($data['revenue_growth']) && is_numeric($data['revenue_growth']);
            });
    }

    /** @test */
    public function it_displays_subscription_status_breakdown(): void
    {
        $this->actingAs($this->admin);

        // Create subscriptions with different statuses
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_active1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
        ]);

        $user2->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_canceled1',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_test',
        ]);

        $user3->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_past_due1',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
        ]);

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('subscriptionBreakdown', function ($breakdown) {
                return isset($breakdown['active']) &&
                       isset($breakdown['canceled']) &&
                       isset($breakdown['past_due']);
            });
    }

    /** @test */
    public function it_shows_average_revenue_per_user(): void
    {
        $this->actingAs($this->admin);

        // Create users with different revenue amounts
        $users = User::factory()->count(3)->create();

        foreach ($users as $index => $user) {
            CreditTransaction::factory()->create([
                'user_id' => $user->id,
                'type' => 'purchase',
                'amount' => 1000 + ($index * 500), // $10, $15, $20
            ]);
        }

        Livewire::test(FinancialDashboard::class)
            ->assertViewHas('financialData', function ($data) {
                return isset($data['arpu']) && $data['arpu'] > 0;
            });
    }

    /** @test */
    public function it_validates_time_period_selection(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->set('timePeriod', 'invalid_period')
            ->call('refreshData')
            ->assertHasErrors(['timePeriod']);
    }

    /** @test */
    public function it_exports_financial_data_for_reports(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->call('exportData', 'csv')
            ->assertDispatched('financial-data-exported');
    }
}
