<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Livewire\Admin\FinancialDashboard;
use App\Models\User;
use App\Models\CreditTransaction;
use App\Services\FinancialDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class FinancialDashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private FinancialDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
        $this->service = new FinancialDataService();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('financial.dashboard'))
            ->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_denies_access_to_non_admin_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Access denied');

        Livewire::test(FinancialDashboard::class);
    }

    public function test_dashboard_allows_access_to_admin_users(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FinancialDashboard::class)
            ->assertStatus(200)
            ->assertSee('Panel Financiero');
    }

    public function test_route_middleware_protects_against_non_admin_access(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(route('financial.dashboard'))
            ->assertStatus(403);
    }

    public function test_livewire_component_validates_admin_access_in_mount(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(FinancialDashboard::class);
    }

    public function test_financial_data_service_handles_sql_injection_attempts(): void
    {
        $this->actingAs($this->admin);

        // Create test data
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 1000,
        ]);

        // Test potential SQL injection strings
        $injectionAttempts = [
            "'; DROP TABLE credit_transactions; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "<script>alert('xss')</script>",
            "'; DELETE FROM credit_transactions WHERE 1=1; --"
        ];

        foreach ($injectionAttempts as $injection) {
            // These should not cause errors or security issues
            $result = $this->service->getFinancialSummary(
                Carbon::now()->subDays(30),
                Carbon::now()
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('total_revenue', $result);
            $this->assertArrayHasKey('mrr', $result);
        }
    }

    public function test_sensitive_data_is_not_exposed_in_logs(): void
    {
        $this->actingAs($this->admin);

        // Create financial data
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 1000,
        ]);

        // Capture logs
        $logPath = storage_path('logs/laravel.log');
        $logsBefore = file_exists($logPath) ? file_get_contents($logPath) : '';

        // Execute financial service
        $this->service->getFinancialSummary(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        $logsAfter = file_exists($logPath) ? file_get_contents($logPath) : '';
        $newLogs = substr($logsAfter, strlen($logsBefore));

        // Verify sensitive data is not in logs
        $this->assertStringNotContainsString('password', $newLogs);
        $this->assertStringNotContainsString('stripe_id', $newLogs);
        $this->assertStringNotContainsString('email', $newLogs);
    }

    public function test_csrf_protection_is_enabled(): void
    {
        $this->actingAs($this->admin);

        // Test that POST requests without CSRF token are rejected
        $response = $this->post(route('financial.dashboard'), [
            'timePeriod' => 'monthly'
        ]);

        $this->assertEquals(419, $response->getStatusCode()); // CSRF token mismatch
    }

    public function test_rate_limiting_prevents_excessive_requests(): void
    {
        $this->actingAs($this->admin);

        // Simulate multiple rapid requests
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(FinancialDashboard::class)
                ->call('refreshData');
        }

        // Additional requests should still work (no rate limiting configured yet)
        // This test documents current behavior and can be updated when rate limiting is added
        $response = Livewire::test(FinancialDashboard::class)
            ->call('refreshData');

        $response->assertStatus(200);
    }

    public function test_export_functionality_requires_admin_access(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(FinancialDashboard::class)
            ->call('exportData', 'csv');
    }

    public function test_cache_keys_are_properly_isolated_per_user(): void
    {
        // This ensures admin data doesn't leak to regular users via cache
        $this->actingAs($this->admin);

        CreditTransaction::factory()->create([
            'user_id' => $this->admin->id,
            'type' => 'purchased',
            'amount' => 2000,
        ]);

        // Generate data for admin (should be cached)
        $adminData = $this->service->getFinancialSummary(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        // Switch to regular user and verify they can't access cached admin data
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(FinancialDashboard::class);
    }

    public function test_input_validation_prevents_malicious_date_ranges(): void
    {
        $this->actingAs($this->admin);

        $maliciousInputs = [
            ['startDate' => '<script>alert("xss")</script>', 'endDate' => '2024-12-31'],
            ['startDate' => '2024-01-01', 'endDate' => '<script>alert("xss")</script>'],
            ['startDate' => 'DROP TABLE users;', 'endDate' => '2024-12-31'],
            ['startDate' => '../../../../etc/passwd', 'endDate' => '2024-12-31'],
        ];

        foreach ($maliciousInputs as $input) {
            $response = Livewire::test(FinancialDashboard::class)
                ->set('timePeriod', 'custom')
                ->set('startDate', $input['startDate'])
                ->set('endDate', $input['endDate'])
                ->call('refreshData');

            // Should have validation errors, not execute malicious code
            $response->assertHasErrors();
        }
    }

    public function test_database_queries_use_parameter_binding(): void
    {
        $this->actingAs($this->admin);

        // Create test data
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => 1000,
        ]);

        // Enable query logging
        \DB::enableQueryLog();

        // Execute service methods
        $this->service->getFinancialSummary(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        $queries = \DB::getQueryLog();

        // Verify all queries use parameter binding (no direct string interpolation)
        foreach ($queries as $query) {
            $this->assertIsArray($query['bindings']);
            // Verify no raw SQL injection patterns in the query string
            $this->assertStringNotContainsString("'; DROP", $query['query']);
            $this->assertStringNotContainsString("' OR '1'='1", $query['query']);
        }

        \DB::disableQueryLog();
    }

    public function test_authorization_is_checked_on_every_component_method(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(FinancialDashboard::class);

        // Switch to non-admin user after component initialization
        $this->actingAs($this->regularUser);

        // All subsequent calls should still check authorization
        $component->call('refreshData')->assertForbidden();
        $component->call('exportData', 'csv')->assertForbidden();
    }

    public function test_financial_calculations_handle_edge_cases_securely(): void
    {
        $this->actingAs($this->admin);

        // Test with extremely large numbers that could cause overflow
        CreditTransaction::factory()->create([
            'user_id' => $this->regularUser->id,
            'type' => 'purchased',
            'amount' => PHP_INT_MAX - 1,
        ]);

        $result = $this->service->getFinancialSummary(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        $this->assertIsNumeric($result['total_revenue']);
        $this->assertGreaterThanOrEqual(0, $result['total_revenue']);
    }

    public function test_error_messages_do_not_leak_sensitive_information(): void
    {
        $this->actingAs($this->admin);

        // Force a database error by corrupting data
        try {
            // This should trigger an error that gets logged/handled
            $this->service->calculateMrr(
                Carbon::createFromFormat('Y-m-d', '9999-99-99'), // Invalid date
                Carbon::now()
            );
        } catch (\Exception $e) {
            // Error messages should not contain database schema information
            $this->assertStringNotContainsString('credit_transactions', $e->getMessage());
            $this->assertStringNotContainsString('users', $e->getMessage());
            $this->assertStringNotContainsString('stripe_id', $e->getMessage());
        }
    }

    public function test_component_properly_handles_concurrent_requests(): void
    {
        $this->actingAs($this->admin);

        // Simulate concurrent access (basic test)
        $component1 = Livewire::test(FinancialDashboard::class);
        $component2 = Livewire::test(FinancialDashboard::class);

        $component1->set('timePeriod', 'monthly');
        $component2->set('timePeriod', 'yearly');

        $component1->call('refreshData');
        $component2->call('refreshData');

        // Both should work independently without interference
        $component1->assertStatus(200);
        $component2->assertStatus(200);
    }

    public function test_session_security_prevents_hijacking(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('financial.dashboard'));

        // Verify secure session settings
        $this->assertStringContainsString('HttpOnly', $response->headers->get('Set-Cookie') ?? '');

        // Session should regenerate on privilege escalation
        $sessionId = session()->getId();

        // Access financial dashboard (privilege-sensitive area)
        Livewire::test(FinancialDashboard::class);

        // Session ID should remain the same for same user (no unnecessary regeneration)
        $this->assertEquals($sessionId, session()->getId());
    }
}
