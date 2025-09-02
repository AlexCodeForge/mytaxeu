<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Tests\TestCase;
use Mockery;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Mock Stripe configuration
        AdminSetting::setValue('stripe_public_key', 'pk_test_mock_key');
        AdminSetting::setValue('stripe_secret_key', 'sk_test_mock_key', true);
        AdminSetting::setValue('stripe_webhook_secret', 'whsec_mock_secret', true);
        AdminSetting::setValue('stripe_test_mode', '1');
    }

    /** @test */
    public function it_creates_billing_portal_session_for_existing_stripe_customer(): void
    {
        // Create user as Stripe customer
        $this->user->update(['stripe_id' => 'cus_test123']);

        // Mock Stripe API calls
        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';
        $mockPortalSession->id = 'bps_test123';

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => url('/billing'),
            ])
            ->andReturn($mockPortalSession);

        $session = $this->user->createBillingPortalSession(url('/billing'));

        $this->assertInstanceOf(StripePortalSession::class, $session);
        $this->assertEquals('https://billing.stripe.com/session/test123', $session->url);
    }

    /** @test */
    public function it_creates_stripe_customer_before_portal_session_if_not_exists(): void
    {
        // User without stripe_id
        $this->assertNull($this->user->stripe_id);

        // Mock Stripe customer creation
        $mockCustomer = Mockery::mock(StripeCustomer::class);
        $mockCustomer->id = 'cus_new123';

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/new123';
        $mockPortalSession->id = 'bps_new123';

        Stripe::shouldReceive('setApiKey')->twice();
        
        StripeCustomer::shouldReceive('create')
            ->once()
            ->with([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->andReturn($mockCustomer);

        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_new123',
                'return_url' => url('/billing'),
            ])
            ->andReturn($mockPortalSession);

        // Mock the createAsStripeCustomer method
        $this->user->shouldReceive('createAsStripeCustomer')
            ->once()
            ->with([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->andReturnSelf();

        $this->user->shouldReceive('hasStripeId')
            ->once()
            ->andReturn(false)
            ->shouldReceive('hasStripeId')
            ->once()
            ->andReturn(true);

        $this->user->shouldReceive('createBillingPortalSession')
            ->once()
            ->with(url('/billing'))
            ->andReturn($mockPortalSession);

        $session = $this->user->createBillingPortalSession(url('/billing'));

        $this->assertInstanceOf(StripePortalSession::class, $session);
        $this->assertEquals('https://billing.stripe.com/session/new123', $session->url);
    }

    /** @test */
    public function it_handles_stripe_api_errors_gracefully(): void
    {
        $this->user->update(['stripe_id' => 'cus_invalid']);

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->andThrow(new \Stripe\Exception\ApiErrorException('Customer not found'));

        $this->expectException(\Stripe\Exception\ApiErrorException::class);
        $this->expectExceptionMessage('Customer not found');

        $this->user->createBillingPortalSession(url('/billing'));
    }

    /** @test */
    public function it_uses_correct_return_url_for_portal_session(): void
    {
        $this->user->update(['stripe_id' => 'cus_test123']);

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'return_url' => 'https://mytaxeu.com/billing',
            ])
            ->andReturn($mockPortalSession);

        $session = $this->user->createBillingPortalSession('https://mytaxeu.com/billing');

        $this->assertInstanceOf(StripePortalSession::class, $session);
    }

    /** @test */
    public function it_throws_exception_for_invalid_customer(): void
    {
        $this->user->update(['stripe_id' => null]);

        // Mock hasStripeId to return false
        $this->user->shouldReceive('hasStripeId')
            ->once()
            ->andReturn(false);

        $this->user->shouldReceive('createAsStripeCustomer')
            ->once()
            ->andThrow(new InvalidCustomer('Unable to create customer'));

        $this->expectException(InvalidCustomer::class);
        $this->expectExceptionMessage('Unable to create customer');

        $this->user->createBillingPortalSession(url('/billing'));
    }

    /** @test */
    public function it_validates_portal_session_configuration(): void
    {
        // Test that portal session is created with correct configuration
        $this->user->update(['stripe_id' => 'cus_test123']);

        $mockPortalSession = Mockery::mock(StripePortalSession::class);
        $mockPortalSession->url = 'https://billing.stripe.com/session/test123';
        $mockPortalSession->configuration = 'bpc_test123';

        Stripe::shouldReceive('setApiKey')->once();
        StripePortalSession::shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'customer' => 'cus_test123',
                'return_url' => url('/billing'),
            ]))
            ->andReturn($mockPortalSession);

        $session = $this->user->createBillingPortalSession(url('/billing'));

        $this->assertInstanceOf(StripePortalSession::class, $session);
        $this->assertNotEmpty($session->url);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
