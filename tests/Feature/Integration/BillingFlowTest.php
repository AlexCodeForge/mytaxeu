<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Tests\TestCase;
use Mockery;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Stripe;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'stripe_id' => 'cus_test123',
            'credits' => 5,
        ]);

        // Mock Stripe configuration
        AdminSetting::setValue('stripe_public_key', 'pk_test_mock_key');
        AdminSetting::setValue('stripe_secret_key', 'sk_test_mock_key', true);
        AdminSetting::setValue('stripe_webhook_secret', 'whsec_mock_secret', true);
        AdminSetting::setValue('stripe_test_mode', '1');
    }

    /** @test */
    public function complete_billing_flow_for_free_user(): void
    {
        // Step 1: User visits billing page
        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Plan Gratuito');
        $response->assertSee('¡Actualiza tu plan!');
        $response->assertSee('Créditos Disponibles');
        $response->assertSee('5'); // Current credits

        // Step 2: User sees upgrade options
        $response->assertSee('Plan Básico');
        $response->assertSee('Plan Profesional');
        $response->assertSee('Plan Empresarial');
        $response->assertSee('€29');
        $response->assertSee('€59');
        $response->assertSee('€99');

        // Step 3: User clicks "Elegir Plan" - should redirect to subscription manager
        $response->assertSee('Elegir Plan');
    }

    /** @test */
    public function complete_billing_flow_for_active_subscriber(): void
    {
        // Create active subscription
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        // Step 1: User visits billing page
        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Plan Básico');
        $response->assertSee('Estado: Activo');
        $response->assertSee('Gestionar Plan');

        // Step 2: User clicks "Gestionar Plan" and gets redirected to Stripe portal
        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andReturn($mockPortalSession);

        $portalResponse = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        $portalResponse->assertRedirect('https://billing.stripe.com/session/test123');

        // Step 3: User returns from portal
        $returnResponse = $this->actingAs($this->user)
            ->get('/billing?portal_return=true');

        $returnResponse->assertStatus(200);
        $returnResponse->assertSessionHas('portal_return', true);
    }

    /** @test */
    public function complete_billing_flow_for_trialing_user(): void
    {
        // Create trialing subscription
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_trial123',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_professional_monthly',
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(14),
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Plan Profesional');
        $response->assertSee('Período de Prueba');
        $response->assertSee('días restantes');
        $response->assertSee('Gestionar Plan');
    }

    /** @test */
    public function complete_billing_flow_for_canceled_subscription(): void
    {
        // Create canceled subscription
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_canceled123',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => now()->addDays(5),
        ]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Suscripción Cancelada');
        $response->assertSee('¡Reactivar Suscripción!');
        $response->assertSee('Gestionar Plan');
    }

    /** @test */
    public function complete_billing_flow_for_past_due_subscription(): void
    {
        // Create past due subscription
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_pastdue123',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Pago Atrasado');
        $response->assertSee('Actualizar Método de Pago');
        $response->assertSee('Gestionar Plan');
    }

    /** @test */
    public function billing_flow_handles_low_credits_warning(): void
    {
        $this->user->update(['credits' => 3]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Atención');
        $response->assertSee('Te quedan pocos créditos');
        $response->assertSee('Considera actualizar tu plan');
    }

    /** @test */
    public function billing_flow_shows_no_warning_for_sufficient_credits(): void
    {
        $this->user->update(['credits' => 15]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('15');
        $response->assertDontSee('Te quedan pocos créditos');
    }

    /** @test */
    public function billing_flow_refresh_functionality_works(): void
    {
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        // Test Livewire refresh functionality
        $this->assertStringContainsString('wire:click="refreshSubscriptionStatus"', $response->getContent());
    }

    /** @test */
    public function billing_flow_navigation_integration(): void
    {
        // Test navigation contains billing link
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);

        // Test billing page is accessible from navigation
        $billingResponse = $this->actingAs($this->user)->get('/billing');
        $billingResponse->assertStatus(200);
    }

    /** @test */
    public function billing_flow_handles_multiple_subscriptions(): void
    {
        // Create old canceled subscription
        $oldSubscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_old123',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'ends_at' => now()->subDays(30),
            'created_at' => now()->subDays(60),
        ]);

        // Create new active subscription
        $newSubscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_new123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_professional_monthly',
            'quantity' => 1,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)->get('/billing');
        
        $response->assertStatus(200);
        $response->assertSee('Plan Profesional'); // Should show active plan
        $response->assertDontSee('Plan Básico'); // Should not show old plan
        $response->assertSee('Estado: Activo');
    }

    /** @test */
    public function billing_flow_error_handling_for_stripe_failures(): void
    {
        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andThrow(new \Stripe\Exception\ApiErrorException('Customer not found'));

        $response = $this->actingAs($this->user)
            ->post('/billing/portal-redirect');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('error');
    }

    /** @test */
    public function billing_flow_maintains_session_across_portal_visits(): void
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
