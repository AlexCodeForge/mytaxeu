<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Billing\BillingPage;
use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class BillingPageTest extends TestCase
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
        ]);

        // Mock Stripe configuration
        AdminSetting::setValue('stripe_public_key', 'pk_test_mock_key');
        AdminSetting::setValue('stripe_secret_key', 'sk_test_mock_key', true);
        AdminSetting::setValue('stripe_webhook_secret', 'whsec_mock_secret', true);
        AdminSetting::setValue('stripe_test_mode', '1');
    }

    /** @test */
    public function it_detects_user_without_subscription(): void
    {
        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', false)
            ->assertSet('subscriptionDetails', null)
            ->assertSee('Plan Gratuito')
            ->assertSee('¡Actualiza tu plan!')
            ->assertSee('Obtén más créditos y funciones avanzadas');
    }

    /** @test */
    public function it_detects_user_with_active_subscription(): void
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

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', true)
            ->assertSet('subscriptionDetails.id', $subscription->id)
            ->assertSet('subscriptionDetails.status', 'active')
            ->assertSee('Plan Básico')
            ->assertSee('Estado: Activo')
            ->assertSee('Gestionar Plan');
    }

    /** @test */
    public function it_detects_user_with_canceled_subscription(): void
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

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', false)
            ->assertSee('Suscripción Cancelada')
            ->assertSee('Tu suscripción terminará el')
            ->assertSee('¡Reactivar Suscripción!');
    }

    /** @test */
    public function it_detects_user_with_past_due_subscription(): void
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

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', false)
            ->assertSee('Pago Atrasado')
            ->assertSee('Hay un problema con tu método de pago')
            ->assertSee('Actualizar Método de Pago');
    }

    /** @test */
    public function it_detects_user_with_trialing_subscription(): void
    {
        // Create trialing subscription
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_trial123',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(14),
            'ends_at' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', true)
            ->assertSee('Período de Prueba')
            ->assertSee('Tu prueba gratuita termina en')
            ->assertSee('días')
            ->assertSee('Gestionar Plan');
    }

    /** @test */
    public function it_displays_subscription_details_for_active_subscription(): void
    {
        $nextBillingDate = now()->addMonth();
        
        // Create active subscription with specific details
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_professional_monthly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
            'created_at' => now()->subDays(15),
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('subscriptionDetails.stripe_price', 'price_professional_monthly')
            ->assertSee('Plan Profesional')
            ->assertSee('Facturación mensual')
            ->assertSee('Próximo pago:');
    }

    /** @test */
    public function it_displays_plan_features_for_active_subscription(): void
    {
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('10 créditos mensuales')
            ->assertSee('Procesamiento de archivos CSV')
            ->assertSee('Notificaciones por email')
            ->assertSee('Soporte por email');
    }

    /** @test */
    public function it_shows_upgrade_options_for_free_users(): void
    {
        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('Plan Básico')
            ->assertSee('Plan Profesional')
            ->assertSee('Plan Empresarial')
            ->assertSee('€29')
            ->assertSee('€59')
            ->assertSee('€99');
    }

    /** @test */
    public function it_shows_current_credit_balance(): void
    {
        $this->user->update(['credits' => 15]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('Créditos Disponibles: 15')
            ->assertSee('15');
    }

    /** @test */
    public function it_handles_multiple_subscriptions_correctly(): void
    {
        // Create multiple subscriptions (old canceled, new active)
        $oldSubscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_old123',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'ends_at' => now()->subDays(5),
        ]);

        $activeSubscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_new123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_professional_monthly',
            'quantity' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSet('hasActiveSubscription', true)
            ->assertSet('subscriptionDetails.id', $activeSubscription->id)
            ->assertSee('Plan Profesional')
            ->assertDontSee('Plan Básico'); // Should show active plan, not old plan
    }

    /** @test */
    public function it_refreshes_subscription_status_when_requested(): void
    {
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->call('refreshSubscriptionStatus')
            ->assertSet('hasActiveSubscription', true);
    }

    /** @test */
    public function it_calculates_trial_days_remaining_correctly(): void
    {
        $trialEndsAt = now()->addDays(7);
        
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_trial123',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => $trialEndsAt,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('7 días'); // Should show days remaining
    }

    /** @test */
    public function it_handles_subscription_without_trial_end_date(): void
    {
        $subscription = $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
            'trial_ends_at' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertDontSee('Período de Prueba')
            ->assertSee('Estado: Activo');
    }

    /** @test */
    public function it_displays_appropriate_cta_buttons_based_on_subscription_status(): void
    {
        // Test free user
        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('Elegir Plan')
            ->assertDontSee('Gestionar Plan');

        // Test active subscriber
        $this->user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_active123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test(BillingPage::class)
            ->assertSee('Gestionar Plan')
            ->assertDontSee('Elegir Plan');
    }
}
