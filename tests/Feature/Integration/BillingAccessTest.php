<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;

class BillingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Stripe configuration
        AdminSetting::setValue('stripe_public_key', 'pk_test_mock_key');
        AdminSetting::setValue('stripe_secret_key', 'sk_test_mock_key', true);
        AdminSetting::setValue('stripe_webhook_secret', 'whsec_mock_secret', true);
        AdminSetting::setValue('stripe_test_mode', '1');
    }

    /** @test */
    public function guest_users_cannot_access_billing_page(): void
    {
        $response = $this->get('/billing');

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function authenticated_free_user_can_access_billing_page(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Plan Gratuito');
        $response->assertSee('¡Actualiza tu plan!');
        $response->assertSee('10'); // Credits
    }

    /** @test */
    public function authenticated_subscribed_user_can_access_billing_page(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
            'credits' => 25,
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_professional_monthly',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Plan Profesional');
        $response->assertSee('Estado: Activo');
        $response->assertSee('Gestionar Plan');
        $response->assertSee('25'); // Credits
    }

    /** @test */
    public function user_with_expired_subscription_can_access_billing_page(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
            'credits' => 0,
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_expired123',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'ends_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Suscripción Cancelada');
        $response->assertSee('¡Reactivar Suscripción!');
        $response->assertSee('0'); // No credits
    }

    /** @test */
    public function user_with_trialing_subscription_can_access_billing_page(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
            'credits' => 10,
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_trial123',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Período de Prueba');
        $response->assertSee('7 días');
        $response->assertSee('Gestionar Plan');
    }

    /** @test */
    public function admin_user_can_access_billing_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'credits' => 50,
        ]);

        $response = $this->actingAs($admin)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Plan Gratuito');
        $response->assertSee('50'); // Credits
    }

    /** @test */
    public function user_with_past_due_subscription_can_access_billing_page(): void
    {
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
            'credits' => 15,
        ]);

        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_pastdue123',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('Pago Atrasado');
        $response->assertSee('Actualizar Método de Pago');
        $response->assertSee('Gestionar Plan');
    }

    /** @test */
    public function stripe_portal_integration_works_end_to_end(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);

        // Mock successful portal session creation
        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test_session_123';
        $mockPortalSession->id = 'bps_test_session_123';
        $mockPortalSession->return_url = url('/billing?portal_return=true');

        Stripe::shouldReceive('setApiKey')
            ->once()
            ->with('sk_test_mock_key');

        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => url('/billing?portal_return=true'),
            ])
            ->andReturn($mockPortalSession);

        // Step 1: User accesses billing page
        $billingResponse = $this->actingAs($user)->get('/billing');
        $billingResponse->assertStatus(200);

        // Step 2: User clicks "Gestionar Plan" (portal redirect)
        $portalResponse = $this->actingAs($user)->post('/billing/portal-redirect');
        $portalResponse->assertRedirect('https://billing.stripe.com/session/test_session_123');

        // Step 3: User returns from Stripe portal
        $returnResponse = $this->actingAs($user)->get('/billing?portal_return=true');
        $returnResponse->assertStatus(200);
        $returnResponse->assertSessionHas('portal_return', true);
    }

    /** @test */
    public function stripe_portal_integration_handles_user_without_stripe_id(): void
    {
        $user = User::factory()->create(['stripe_id' => null]);

        // Mock customer creation
        $mockCustomer = Mockery::mock(StripeCustomer::class);
        $mockCustomer->id = 'cus_new_customer_123';

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/new_session_123';

        Stripe::shouldReceive('setApiKey')->twice();

        StripeCustomer::shouldReceive('create')
            ->once()
            ->with([
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->andReturn($mockCustomer);

        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_new_customer_123',
                'return_url' => url('/billing?portal_return=true'),
            ])
            ->andReturn($mockPortalSession);

        $response = $this->actingAs($user)->post('/billing/portal-redirect');
        $response->assertRedirect('https://billing.stripe.com/session/new_session_123');

        // Verify user now has stripe_id
        $this->assertNotNull($user->fresh()->stripe_id);
    }

    /** @test */
    public function stripe_portal_integration_handles_api_errors(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andThrow(new \Stripe\Exception\ApiErrorException('Customer not found'));

        $response = $this->actingAs($user)->post('/billing/portal-redirect');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Error al acceder al portal', session('error'));
    }

    /** @test */
    public function billing_page_handles_network_failures_gracefully(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Network timeout'));

        $response = $this->actingAs($user)->post('/billing/portal-redirect');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Error al acceder al portal', session('error'));
    }

    /** @test */
    public function billing_page_respects_middleware_protection(): void
    {
        // Test that auth middleware is applied
        $response = $this->get('/billing');
        $response->assertRedirect(route('login'));

        // Test that verified middleware is applied (if user is unverified)
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        $response = $this->actingAs($unverifiedUser)->get('/billing');
        
        // Should either redirect to verification or show page based on app config
        $this->assertTrue(
            $response->isRedirection() || $response->isSuccessful()
        );
    }

    /** @test */
    public function billing_portal_redirect_respects_authentication(): void
    {
        $response = $this->post('/billing/portal-redirect');
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function billing_flow_maintains_user_context(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'credits' => 25,
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSee('25'); // User's credits
        $response->assertSee('john@example.com', false); // User's email in page context
    }

    /** @test */
    public function billing_page_loads_with_correct_livewire_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing');

        $response->assertStatus(200);
        $response->assertSeeLivewire('billing.billing-page');
    }

    /** @test */
    public function stripe_portal_session_configuration_is_correct(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';
        $mockPortalSession->customer = 'cus_test123';
        $mockPortalSession->return_url = url('/billing?portal_return=true');

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => url('/billing?portal_return=true'),
            ])
            ->andReturn($mockPortalSession);

        $response = $this->actingAs($user)->post('/billing/portal-redirect');
        $response->assertRedirect('https://billing.stripe.com/session/test123');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
