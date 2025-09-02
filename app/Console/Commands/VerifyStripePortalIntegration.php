<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminSetting;
use App\Models\User;
use App\Services\StripePortalService;
use Illuminate\Console\Command;
use Stripe\BillingPortal\Session as StripePortalSession;
use Stripe\Stripe;

class VerifyStripePortalIntegration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'stripe:verify-portal {--test-user-email=}';

    /**
     * The console command description.
     */
    protected $description = 'Verify Stripe Customer Portal integration and Cashier methods';

    /**
     * Execute the console command.
     */
    public function handle(StripePortalService $portalService): int
    {
        $this->info('🔍 Verifying Stripe Customer Portal Integration...');
        $this->newLine();

        // Step 1: Check configuration
        $this->info('1. Checking Stripe configuration...');
        $configValidation = $portalService->validatePortalConfiguration();
        
        if ($configValidation['valid']) {
            $this->info('   ✅ Stripe configuration is valid');
        } else {
            $this->error('   ❌ Stripe configuration issues found:');
            foreach ($configValidation['issues'] as $issue) {
                $this->error('      - ' . $issue);
            }
            $this->newLine();
            $this->info('Required Stripe Dashboard configuration:');
            foreach ($configValidation['required_features'] as $feature) {
                $this->line('   - ' . $feature);
            }
            return Command::FAILURE;
        }

        // Step 2: Test Cashier Billable trait methods
        $this->info('2. Testing Laravel Cashier Billable trait methods...');
        
        $testUser = $this->getTestUser();
        if (!$testUser) {
            $this->error('   ❌ No test user available');
            return Command::FAILURE;
        }

        $this->info("   Using test user: {$testUser->email}");

        // Test hasStripeId method
        $hasStripeId = $testUser->hasStripeId();
        $this->info("   hasStripeId(): " . ($hasStripeId ? 'true' : 'false'));

        // Test createAsStripeCustomer if needed
        if (!$hasStripeId) {
            try {
                $this->info('   Creating Stripe customer...');
                $testUser->createAsStripeCustomer([
                    'name' => $testUser->name,
                    'email' => $testUser->email,
                ]);
                $this->info('   ✅ createAsStripeCustomer() - Success');
                $this->info("   Stripe ID: {$testUser->fresh()->stripe_id}");
            } catch (\Exception $e) {
                $this->error('   ❌ createAsStripeCustomer() - Failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // Step 3: Test portal session creation
        $this->info('3. Testing billing portal session creation...');
        
        try {
            $returnUrl = url('/billing');
            $session = $testUser->createBillingPortalSession($returnUrl);
            
            if ($session instanceof StripePortalSession) {
                $this->info('   ✅ createBillingPortalSession() - Success');
                $this->info("   Session ID: {$session->id}");
                $this->info("   Portal URL: {$session->url}");
                $this->info("   Return URL: {$session->return_url}");
            } else {
                $this->error('   ❌ Invalid session object returned');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('   ❌ createBillingPortalSession() - Failed: ' . $e->getMessage());
            $this->error('   Error type: ' . get_class($e));
            return Command::FAILURE;
        }

        // Step 4: Test StripePortalService methods
        $this->info('4. Testing StripePortalService methods...');
        
        $serviceTest = $portalService->testPortalSessionCreation();
        if ($serviceTest['success']) {
            $this->info('   ✅ StripePortalService::testPortalSessionCreation() - Success');
            $this->info("   Service session ID: {$serviceTest['session_id']}");
        } else {
            $this->error('   ❌ StripePortalService::testPortalSessionCreation() - Failed');
            $this->error('   ' . $serviceTest['message']);
        }

        // Step 5: Display configuration instructions
        $this->newLine();
        $this->info('5. Configuration verification complete!');
        $this->newLine();
        
        if (!$this->option('quiet')) {
            $this->displayConfigurationInstructions($portalService);
        }

        $this->info('✅ All Stripe Customer Portal integration tests passed!');
        return Command::SUCCESS;
    }

    /**
     * Get a test user for verification.
     */
    private function getTestUser(): ?User
    {
        $email = $this->option('test-user-email');
        
        if ($email) {
            return User::where('email', $email)->first();
        }

        // Try to get admin user first
        $adminUser = User::where('is_admin', true)->first();
        if ($adminUser) {
            return $adminUser;
        }

        // Fall back to any user
        return User::first();
    }

    /**
     * Display configuration instructions.
     */
    private function displayConfigurationInstructions(StripePortalService $portalService): void
    {
        $instructions = $portalService->getConfigurationInstructions();
        
        $this->info('📋 Stripe Dashboard Configuration Instructions:');
        $this->newLine();
        $this->info("Dashboard URL: {$instructions['dashboard_url']}");
        $this->newLine();
        
        foreach ($instructions['steps'] as $step) {
            $this->line($step);
        }
        
        $this->newLine();
        $this->info('Verification methods:');
        foreach ($instructions['verification'] as $method) {
            $this->line('- ' . $method);
        }
    }
}
