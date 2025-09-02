<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\AdminSetting;
use App\Models\User;
use App\Services\StripePortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;
use Mockery;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Stripe;

class PortalRedirectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private StripePortalService $portalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'stripe_id' => 'cus_test123',
        ]);

        $this->portalService = app(StripePortalService::class);

        // Mock Stripe configuration
        AdminSetting::setValue('stripe_public_key', 'pk_test_mock_key');
        AdminSetting::setValue('stripe_secret_key', 'sk_test_mock_key', true);
        AdminSetting::setValue('stripe_webhook_secret', 'whsec_mock_secret', true);
        AdminSetting::setValue('stripe_test_mode', '1');
    }

    /** @test */
    public function it_redirects_authenticated_user_to_stripe_portal(): void
    {
        // Mock Stripe portal session
        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';
        $mockPortalSession->id = 'bps_test123';
        $mockPortalSession->return_url = url('/billing');

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => url('/billing'),
            ])
            ->andReturn($mockPortalSession);

        // Act as authenticated user
        $response = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        // Should redirect to Stripe portal
        $response->assertRedirect('https://billing.stripe.com/session/test123');
    }

    /** @test */
    public function it_requires_authentication_for_portal_access(): void
    {
        // Unauthenticated request
        $response = $this->post('/billing/portal-redirect');

        // Should redirect to login
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_handles_portal_return_with_success_message(): void
    {
        // Act as authenticated user and access billing return
        $response = $this->actingAs($this->user)
            ->get('/billing?portal_return=true');

        $response->assertStatus(200);
        $response->assertSessionHas('success', 'Has regresado del portal de facturación.');
    }

    /** @test */
    public function it_handles_portal_return_without_message_for_normal_access(): void
    {
        // Normal billing page access
        $response = $this->actingAs($this->user)
            ->get('/billing');

        $response->assertStatus(200);
        $response->assertSessionMissing('success');
    }

    /** @test */
    public function it_creates_portal_session_with_correct_return_url(): void
    {
        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';
        $mockPortalSession->return_url = url('/billing?portal_return=true');

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => url('/billing?portal_return=true'),
            ])
            ->andReturn($mockPortalSession);

        $session = $this->portalService->createPortalSession(
            $this->user,
            url('/billing?portal_return=true')
        );

        $this->assertEquals(url('/billing?portal_return=true'), $session->return_url);
    }

    /** @test */
    public function it_handles_stripe_api_errors_during_redirect(): void
    {
        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andThrow(new \Stripe\Exception\ApiErrorException('Customer not found'));

        $response = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        // Should redirect back with error message
        $response->assertRedirect('/billing');
        $response->assertSessionHas('error');
    }

    /** @test */
    public function it_validates_user_has_stripe_customer_before_portal_redirect(): void
    {
        // User without Stripe customer ID
        $userWithoutStripe = User::factory()->create([
            'stripe_id' => null,
        ]);

        // Mock customer creation
        Stripe::shouldReceive('setApiKey')->twice();
        
        $mockCustomer = Mockery::mock(\Stripe\Customer::class);
        $mockCustomer->id = 'cus_new123';
        
        \Stripe\Customer::shouldReceive('create')
            ->once()
            ->andReturn($mockCustomer);

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/new123';

        StripePortalSession::shouldReceive('create')
            ->once()
            ->andReturn($mockPortalSession);

        $response = $this->actingAs($userWithoutStripe)
            ->post('/billing/portal-redirect');

        // Should still redirect to portal after creating customer
        $response->assertRedirect('https://billing.stripe.com/session/new123');
        
        // Verify user now has stripe_id
        $this->assertNotNull($userWithoutStripe->fresh()->stripe_id);
    }

    /** @test */
    public function it_preserves_session_data_during_portal_flow(): void
    {
        // Set some session data
        session(['test_data' => 'preserved']);

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andReturn($mockPortalSession);

        $response = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        // Session data should be preserved
        $this->assertEquals('preserved', session('test_data'));
    }

    /** @test */
    public function it_handles_concurrent_portal_session_creation(): void
    {
        // Simulate multiple concurrent requests
        $mockPortalSession1 = Mockery::mock(StripePortalSession::class);
        $mockPortalSession1->url = 'https://billing.stripe.com/session/test123_1';
        
        $mockPortalSession2 = Mockery::mock(StripePortalSession::class);
        $mockPortalSession2->url = 'https://billing.stripe.com/session/test123_2';

        Stripe::shouldReceive('setApiKey')->twice();
        StripePortalSession::shouldReceive('create')
            ->twice()
            ->andReturn($mockPortalSession1, $mockPortalSession2);

        // First request
        $response1 = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        // Second request (should work independently)
        $response2 = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        $response1->assertRedirect('https://billing.stripe.com/session/test123_1');
        $response2->assertRedirect('https://billing.stripe.com/session/test123_2');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
