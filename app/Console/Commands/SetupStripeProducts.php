<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class SetupStripeProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:setup-products {--test : Use test mode keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Stripe products and prices for credit-based subscription plans';

    /**
     * Credit plans configuration.
     */
    protected array $creditPlans = [
        [
            'name' => 'Plan Básico',
            'description' => 'Plan básico de 10 créditos mensuales para gestión de declaraciones fiscales',
            'credits' => 10,
            'price_eur' => 2900, // €29.00 in cents
            'billing_period' => 'month',
        ],
        [
            'name' => 'Plan Profesional',
            'description' => 'Plan profesional de 25 créditos mensuales para consultorías fiscales',
            'credits' => 25,
            'price_eur' => 5900, // €59.00 in cents
            'billing_period' => 'month',
        ],
        [
            'name' => 'Plan Empresarial',
            'description' => 'Plan empresarial de 50 créditos mensuales para grandes consultorías',
            'credits' => 50,
            'price_eur' => 9900, // €99.00 in cents
            'billing_period' => 'month',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $testMode = $this->option('test');

        // Set Stripe API key
        $apiKey = $testMode
            ? config('cashier.secret')
            : config('cashier.secret');

        if (empty($apiKey)) {
            $this->error('Stripe API key not configured. Please set STRIPE_SECRET in your .env file.');
            return 1;
        }

        Stripe::setApiKey($apiKey);

        $this->info('Setting up Stripe products and prices for credit plans...');
        if ($testMode) {
            $this->warn('Using TEST mode');
        }

        try {
            foreach ($this->creditPlans as $plan) {
                $this->createCreditPlan($plan);
            }

            $this->info('✅ Successfully created all Stripe products and prices!');
            $this->line('');
            $this->info('Next steps:');
            $this->line('1. Configure webhook endpoints in your Stripe dashboard');
            $this->line('2. Set your webhook secret in STRIPE_WEBHOOK_SECRET');
            $this->line('3. Test the subscription flow in your application');

            return 0;

        } catch (ApiErrorException $e) {
            $this->error('Stripe API Error: ' . $e->getMessage());
            Log::error('Stripe product setup failed', [
                'error' => $e->getMessage(),
                'type' => $e->getStripeCode(),
            ]);
            return 1;
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            Log::error('Product setup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Create a credit plan product and price in Stripe.
     */
    protected function createCreditPlan(array $plan): void
    {
        $this->line("Creating plan: {$plan['name']} ({$plan['credits']} credits)");

        // Create product
        $product = Product::create([
            'name' => $plan['name'],
            'description' => $plan['description'],
            'metadata' => [
                'credits' => (string) $plan['credits'],
                'type' => 'credit_plan',
            ],
        ]);

        $this->info("  ✓ Product created: {$product->id}");

        // Create price
        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => $plan['price_eur'],
            'currency' => 'eur',
            'recurring' => [
                'interval' => $plan['billing_period'],
            ],
            'metadata' => [
                'credits' => (string) $plan['credits'],
            ],
        ]);

        $this->info("  ✓ Price created: {$price->id} (€" . number_format($plan['price_eur'] / 100, 2) . "/{$plan['billing_period']})");

        // Display information for manual configuration
        $this->line("  📋 Product ID: {$product->id}");
        $this->line("  📋 Price ID: {$price->id}");
        $this->line('');

        Log::info('Stripe credit plan created', [
            'plan_name' => $plan['name'],
            'product_id' => $product->id,
            'price_id' => $price->id,
            'credits' => $plan['credits'],
            'price_eur' => $plan['price_eur'],
        ]);
    }
}
