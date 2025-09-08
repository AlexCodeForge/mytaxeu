<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\MonthlySalesReport;
use App\Services\ReportingService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMonthlySalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:monthly-report {--month=last}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly executive sales report to administrators';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting monthly sales report generation...');

            // Determine the month to report on
            $monthData = $this->getMonthPeriod();
            $startDate = $monthData['start'];
            $endDate = $monthData['end'];
            $monthPeriod = $monthData['period'];

            $this->info("Generating executive report for: {$monthPeriod}");

            // Generate comprehensive report data
            $reportingService = app(ReportingService::class);
            $reportData = $reportingService->getDashboardData($startDate, $endDate);

            // Enhance with executive-level insights
            $reportData = $this->enhanceExecutiveData($reportData, $startDate, $endDate);

            // Send to administrators (monthly reports are always sent)
            $emailService = app(EmailService::class);
            $adminEmails = $this->getAdminEmails();

            $sent = 0;
            foreach ($adminEmails as $adminEmail) {
                try {
                    $notification = new MonthlySalesReport($reportData, $monthPeriod);

                    $emailService->sendNotificationToEmail($adminEmail, $notification);
                    $sent++;

                    $this->info("✓ Monthly executive report sent to: {$adminEmail}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to send to {$adminEmail}: {$e->getMessage()}");
                    Log::error('Failed to send monthly report', [
                        'email' => $adminEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Monthly sales report sent', [
                'month_period' => $monthPeriod,
                'total_sales' => $reportData['sales']['total_sales'] ?? 0,
                'total_revenue' => $reportData['sales']['total_revenue'] ?? 0,
                'growth_rate' => $reportData['growth']['revenue_growth_rate'] ?? 0,
                'emails_sent' => $sent,
            ]);

            $this->info("Monthly executive report completed. Sent to {$sent} administrator(s).");

            // Show summary in console
            $this->displayExecutiveSummary($reportData);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to send monthly sales report: {$e->getMessage()}");
            Log::error('Monthly sales report command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Get the month period to report on.
     */
    protected function getMonthPeriod(): array
    {
        $monthOption = $this->option('month');

        if ($monthOption === 'last') {
            // Last complete month
            $startDate = Carbon::now()->subMonth()->startOfMonth();
            $endDate = Carbon::now()->subMonth()->endOfMonth();
        } elseif ($monthOption === 'current') {
            // Current month (from start to today)
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now();
        } else {
            // Parse custom month (e.g., "2024-01" or "January 2024")
            $date = Carbon::parse($monthOption);
            $startDate = $date->copy()->startOfMonth();
            $endDate = $date->copy()->endOfMonth();
        }

        $period = $startDate->format('F Y');

        return [
            'start' => $startDate,
            'end' => $endDate,
            'period' => $period,
        ];
    }

    /**
     * Enhance report data with executive-level insights.
     */
    protected function enhanceExecutiveData(array $reportData, Carbon $startDate, Carbon $endDate): array
    {
        // Add key performance indicators
        $reportData['kpis'] = $this->calculateKPIs($reportData);

        // Add competitive analysis placeholder
        $reportData['market_insights'] = $this->getMarketInsights($reportData);

        // Add forecasting data
        $reportData['forecasts'] = $this->generateForecasts($reportData);

        // Add goal tracking
        $reportData['goal_tracking'] = $this->trackGoals($reportData);

        return $reportData;
    }

    /**
     * Calculate key performance indicators.
     */
    protected function calculateKPIs(array $reportData): array
    {
        $revenue = $reportData['sales']['total_revenue'] ?? 0;
        $sales = $reportData['sales']['total_sales'] ?? 0;
        $customers = $reportData['customers']['new_users'] ?? 0;

        return [
            'revenue_per_customer' => $customers > 0 ? $revenue / $customers : 0,
            'monthly_recurring_revenue' => $revenue * 0.85, // Estimate
            'customer_acquisition_cost' => $customers > 0 ? ($revenue * 0.3) / $customers : 0,
            'churn_rate' => 5.2, // Placeholder
            'lifetime_value' => 1250.0, // Placeholder
        ];
    }

    /**
     * Get market insights (placeholder for future implementation).
     */
    protected function getMarketInsights(array $reportData): array
    {
        return [
            'market_position' => 'Growing',
            'competitive_advantage' => 'Specialized EU tax compliance',
            'opportunities' => [
                'Expansion to new EU markets',
                'Enterprise customer segment',
                'API integration partnerships',
            ],
            'threats' => [
                'Increased competition',
                'Regulatory changes',
            ],
        ];
    }

    /**
     * Generate forecast data.
     */
    protected function generateForecasts(array $reportData): array
    {
        $currentRevenue = $reportData['sales']['total_revenue'] ?? 0;
        $growthRate = ($reportData['growth']['revenue_growth_rate'] ?? 0) / 100;

        return [
            'next_month_revenue' => $currentRevenue * (1 + $growthRate),
            'quarter_projection' => $currentRevenue * 3 * (1 + $growthRate),
            'confidence_level' => 'Medium',
            'factors' => [
                'Seasonal trends',
                'Current growth trajectory',
                'Market conditions',
            ],
        ];
    }

    /**
     * Track progress against goals.
     */
    protected function trackGoals(array $reportData): array
    {
        $revenue = $reportData['sales']['total_revenue'] ?? 0;
        $sales = $reportData['sales']['total_sales'] ?? 0;
        $customers = $reportData['customers']['new_users'] ?? 0;

        return [
            'revenue_goal' => [
                'target' => 10000,
                'actual' => $revenue,
                'percentage' => min(100, ($revenue / 10000) * 100),
                'status' => $revenue >= 10000 ? 'achieved' : 'in_progress',
            ],
            'sales_goal' => [
                'target' => 80,
                'actual' => $sales,
                'percentage' => min(100, ($sales / 80) * 100),
                'status' => $sales >= 80 ? 'achieved' : 'in_progress',
            ],
            'customer_goal' => [
                'target' => 50,
                'actual' => $customers,
                'percentage' => min(100, ($customers / 50) * 100),
                'status' => $customers >= 50 ? 'achieved' : 'in_progress',
            ],
        ];
    }

    /**
     * Display executive summary in console.
     */
    protected function displayExecutiveSummary(array $reportData): void
    {
        $this->info('');
        $this->info('=== EXECUTIVE SUMMARY ===');
        $this->info("Revenue: €" . number_format($reportData['sales']['total_revenue'] ?? 0, 2));
        $this->info("Sales: " . number_format($reportData['sales']['total_sales'] ?? 0));
        $this->info("Growth: " . number_format($reportData['growth']['revenue_growth_rate'] ?? 0, 1) . "%");
        $this->info("New Customers: " . number_format($reportData['customers']['new_users'] ?? 0));
        $this->info("Success Rate: " . number_format($reportData['jobs']['success_rate'] ?? 0, 1) . "%");
        $this->info('========================');
    }

    /**
     * Get administrator email addresses.
     */
    protected function getAdminEmails(): array
    {
        $emails = array_filter([
            config('emails.admin_email'),
            config('emails.support_email'),
        ]);

        if (empty($emails)) {
            $emails = ['admin@mytaxeu.com']; // Fallback
        }

        return $emails;
    }
}
