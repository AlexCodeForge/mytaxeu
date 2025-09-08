<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\WeeklySalesReport;
use App\Services\ReportingService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklySalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:weekly-report {--week=last}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly sales report to administrators';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting weekly sales report generation...');

            // Determine the week to report on
            $weekData = $this->getWeekPeriod();
            $startDate = $weekData['start'];
            $endDate = $weekData['end'];
            $weekPeriod = $weekData['period'];

            $this->info("Generating report for week: {$weekPeriod}");

            // Generate comprehensive report data
            $reportingService = app(ReportingService::class);
            $reportData = $reportingService->getDashboardData($startDate, $endDate);

            // Check if we should send the report
            if (!$this->shouldSendReport($reportData)) {
                $this->info('No significant activity detected for the week. Skipping report.');
                return self::SUCCESS;
            }

            // Send to administrators
            $emailService = app(EmailService::class);
            $adminEmails = $this->getAdminEmails();

            $sent = 0;
            foreach ($adminEmails as $adminEmail) {
                try {
                    $notification = new WeeklySalesReport($reportData, $weekPeriod);

                    $emailService->sendNotificationToEmail($adminEmail, $notification);
                    $sent++;

                    $this->info("✓ Weekly report sent to: {$adminEmail}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to send to {$adminEmail}: {$e->getMessage()}");
                    Log::error('Failed to send weekly report', [
                        'email' => $adminEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Weekly sales report sent', [
                'week_period' => $weekPeriod,
                'total_sales' => $reportData['sales']['total_sales'] ?? 0,
                'total_revenue' => $reportData['sales']['total_revenue'] ?? 0,
                'emails_sent' => $sent,
            ]);

            $this->info("Weekly sales report completed. Sent to {$sent} administrator(s).");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to send weekly sales report: {$e->getMessage()}");
            Log::error('Weekly sales report command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Get the week period to report on.
     */
    protected function getWeekPeriod(): array
    {
        $weekOption = $this->option('week');

        if ($weekOption === 'last') {
            // Last complete week (Monday to Sunday)
            $endDate = Carbon::now()->previous(Carbon::SUNDAY);
            $startDate = $endDate->copy()->previous(Carbon::MONDAY);
        } elseif ($weekOption === 'current') {
            // Current week (Monday to today or Sunday if complete)
            $startDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $endDate = Carbon::now()->dayOfWeek === Carbon::SUNDAY
                ? Carbon::now()
                : Carbon::now()->previous(Carbon::SUNDAY);
        } else {
            // Parse custom date and find the week it belongs to
            $date = Carbon::parse($weekOption);
            $startDate = $date->copy()->startOfWeek(Carbon::MONDAY);
            $endDate = $date->copy()->endOfWeek(Carbon::SUNDAY);
        }

        $period = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');

        return [
            'start' => $startDate,
            'end' => $endDate,
            'period' => $period,
        ];
    }

    /**
     * Determine if the report should be sent based on activity.
     */
    protected function shouldSendReport(array $reportData): bool
    {
        $totalSales = $reportData['sales']['total_sales'] ?? 0;
        $totalJobs = $reportData['jobs']['total_jobs'] ?? 0;

        // Send if there were any sales or significant job activity
        if ($totalSales > 0 || $totalJobs > 10) {
            return true;
        }

        // Always send weekly reports for visibility, even with low activity
        return true;
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
