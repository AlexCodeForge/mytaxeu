<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountCode;
use App\Services\StripeDiscountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDiscountCodesWithStripe extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'discount-codes:sync {--dry-run : Only show what would be synchronized without making changes} {--fix : Attempt to fix synchronization issues}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize discount codes between local database and Stripe';

    private StripeDiscountService $stripeService;

    public function __construct(StripeDiscountService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting discount codes synchronization with Stripe...');

        $dryRun = $this->option('dry-run');
        $fix = $this->option('fix');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $localCodes = DiscountCode::all();
        $issues = [];
        $stats = [
            'total' => $localCodes->count(),
            'synced' => 0,
            'missing_from_stripe' => 0,
            'usage_mismatched' => 0,
            'fixed' => 0,
            'errors' => 0,
        ];

        $this->info("📊 Found {$stats['total']} discount codes in local database");
        $this->newLine();

        foreach ($localCodes as $code) {
            $this->line("🎫 Checking: {$code->code} (Stripe ID: {$code->stripe_coupon_id})");

            try {
                // Check if coupon exists in Stripe
                $stripeCoupon = $this->stripeService->getCoupon($code->stripe_coupon_id);

                if (!$stripeCoupon) {
                    $this->error("  ❌ Missing from Stripe");
                    $issues[] = [
                        'type' => 'missing_from_stripe',
                        'code' => $code->code,
                        'stripe_coupon_id' => $code->stripe_coupon_id,
                        'issue' => 'Coupon exists in local DB but not in Stripe',
                    ];
                    $stats['missing_from_stripe']++;
                    continue;
                }

                // Check usage synchronization
                $localUsedCount = (int) $code->used_count;
                $stripeTimesRedeemed = (int) $stripeCoupon->times_redeemed;

                if ($localUsedCount !== $stripeTimesRedeemed) {
                    $this->warn("  ⚠️  Usage mismatch: Local={$localUsedCount}, Stripe={$stripeTimesRedeemed}");
                    $issues[] = [
                        'type' => 'usage_mismatch',
                        'code' => $code->code,
                        'local_count' => $localUsedCount,
                        'stripe_count' => $stripeTimesRedeemed,
                        'issue' => 'Usage count differs between local DB and Stripe',
                    ];
                    $stats['usage_mismatched']++;

                    // Fix usage mismatch if requested
                    if ($fix && !$dryRun) {
                        $code->used_count = $stripeTimesRedeemed;
                        $code->save();
                        $this->info("  ✅ Fixed usage count: {$localUsedCount} → {$stripeTimesRedeemed}");
                        $stats['fixed']++;
                    }
                } else {
                    $this->info("  ✅ Synchronized");
                    $stats['synced']++;
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                $issues[] = [
                    'type' => 'error',
                    'code' => $code->code,
                    'error' => $e->getMessage(),
                ];
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info('📈 Synchronization Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total codes', $stats['total']],
                ['Synchronized', $stats['synced']],
                ['Missing from Stripe', $stats['missing_from_stripe']],
                ['Usage mismatched', $stats['usage_mismatched']],
                ['Fixed', $stats['fixed']],
                ['Errors', $stats['errors']],
            ]
        );

        if (!empty($issues)) {
            $this->newLine();
            $this->warn('🚨 Issues Found:');

            foreach ($issues as $issue) {
                $this->line("• {$issue['code']}: {$issue['issue']}");
                if (isset($issue['local_count']) && isset($issue['stripe_count'])) {
                    $this->line("  Local: {$issue['local_count']}, Stripe: {$issue['stripe_count']}");
                }
                if (isset($issue['error'])) {
                    $this->line("  Error: {$issue['error']}");
                }
            }

            if (!$fix && $stats['usage_mismatched'] > 0) {
                $this->newLine();
                $this->info('💡 To fix usage mismatches, run: php artisan discount-codes:sync --fix');
            }
        }

        if ($stats['missing_from_stripe'] > 0) {
            $this->newLine();
            $this->warn('⚠️  Some discount codes are missing from Stripe. You may need to recreate them manually.');
        }

        // Log the sync results
        Log::info('Discount codes sync completed', [
            'stats' => $stats,
            'issues_count' => count($issues),
            'dry_run' => $dryRun,
            'fix_mode' => $fix,
        ]);

        return self::SUCCESS;
    }
}
