<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePortalService
{
    /**
     * Create a billing portal session for the given user.
     */
    public function createPortalSession(User $user, ?string $returnUrl = null): StripePortalSession
    {
        $returnUrl = $returnUrl ?: url('/billing');

        // Set Stripe API key
        $stripeConfig = AdminSetting::getStripeConfig();
        if (empty($stripeConfig['secret_key'])) {
            throw new \RuntimeException('Stripe secret key not configured');
        }

        Stripe::setApiKey($stripeConfig['secret_key']);

        // Ensure user has a Stripe customer ID
        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer([
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        try {
            return StripePortalSession::create([
                'customer' => $user->stripe_id,
                'return_url' => $returnUrl,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe portal session', [
                'user_id' => $user->id,
                'stripe_id' => $user->stripe_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that Stripe Customer Portal is properly configured.
     */
    public function validatePortalConfiguration(): array
    {
        $issues = [];

        // Check Stripe configuration
        $stripeConfig = AdminSetting::getStripeConfig();
        if (empty($stripeConfig['secret_key'])) {
            $issues[] = 'Stripe secret key is not configured';
        }

        if (empty($stripeConfig['public_key'])) {
            $issues[] = 'Stripe public key is not configured';
        }

        // Test API connection if keys are available
        if (!empty($stripeConfig['secret_key'])) {
            try {
                Stripe::setApiKey($stripeConfig['secret_key']);
                \Stripe\Account::retrieve();
            } catch (ApiErrorException $e) {
                $issues[] = 'Invalid Stripe API key: ' . $e->getMessage();
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'configuration_url' => 'https://dashboard.stripe.com/settings/billing/portal',
            'required_features' => [
                'Customer portal enabled',
                'Invoice history access enabled',
                'Payment method management enabled',
                'Subscription management enabled',
                'Return URL configured to: ' . url('/billing'),
            ],
        ];
    }

    /**
     * Test portal session creation with a test user.
     */
    public function testPortalSessionCreation(): array
    {
        try {
            // Use admin user for testing if available
            $testUser = User::where('is_admin', true)->first();
            if (!$testUser) {
                return [
                    'success' => false,
                    'message' => 'No admin user available for testing',
                ];
            }

            // Attempt to create portal session
            $session = $this->createPortalSession($testUser, url('/billing'));

            return [
                'success' => true,
                'message' => 'Portal session created successfully',
                'session_id' => $session->id,
                'portal_url' => $session->url,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create portal session: ' . $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
    }

    /**
     * Get Stripe Customer Portal dashboard configuration instructions.
     */
    public function getConfigurationInstructions(): array
    {
        return [
            'title' => 'Stripe Customer Portal Configuration',
            'dashboard_url' => 'https://dashboard.stripe.com/settings/billing/portal',
            'steps' => [
                '1. Navigate to Stripe Dashboard > Settings > Customer Portal',
                '2. Enable the Customer Portal',
                '3. Configure Features:',
                '   - Enable "Invoice history"',
                '   - Enable "Payment method management"',
                '   - Enable "Subscription management"',
                '   - Enable "Subscription cancellation"',
                '4. Set Business Information:',
                '   - Business name: MyTaxEU',
                '   - Customer support email: support@mytaxeu.com',
                '   - Terms of service URL (optional)',
                '   - Privacy policy URL (optional)',
                '5. Configure Return URL:',
                '   - Default return URL: ' . url('/billing'),
                '6. Save configuration',
                '7. Test the portal with a test customer',
            ],
            'verification' => [
                'Use the validatePortalConfiguration() method to verify setup',
                'Use the testPortalSessionCreation() method to test functionality',
            ],
        ];
    }
}
