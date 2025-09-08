<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\DailyJobStatusReport;
use App\Services\ReportingService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyJobReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:daily-report {--date=yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily job status report to administrators';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting daily job report generation...');

            // Parse the date parameter
            $targetDate = $this->option('date') === 'yesterday'
                ? Carbon::yesterday()
                : Carbon::parse($this->option('date'));

            $this->info("Generating report for: {$targetDate->format('Y-m-d')}");

            // Generate report data
            $reportingService = app(ReportingService::class);
            $startDate = $targetDate->copy()->startOfDay();
            $endDate = $targetDate->copy()->endOfDay();

            $jobData = $reportingService->getJobStatistics($startDate, $endDate);

            // Add additional context data
            $jobData = $this->enhanceJobData($jobData, $targetDate);

            // Check if we should send the report
            if (!$this->shouldSendReport($jobData)) {
                $this->info('No significant activity detected. Skipping report.');
                return self::SUCCESS;
            }

            // Send to administrators
            $emailService = app(EmailService::class);
            $adminEmails = $this->getAdminEmails();

            $sent = 0;
            foreach ($adminEmails as $adminEmail) {
                try {
                    $notification = new DailyJobStatusReport($jobData, $targetDate->format('Y-m-d'));

                    $emailService->sendNotificationToEmail($adminEmail, $notification);
                    $sent++;

                    $this->info("✓ Report sent to: {$adminEmail}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to send to {$adminEmail}: {$e->getMessage()}");
                    Log::error('Failed to send daily report', [
                        'email' => $adminEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Daily job report sent', [
                'date' => $targetDate->format('Y-m-d'),
                'total_jobs' => $jobData['jobs']['total_jobs'] ?? 0,
                'success_rate' => $jobData['jobs']['success_rate'] ?? 0,
                'emails_sent' => $sent,
            ]);

            $this->info("Daily report completed. Sent to {$sent} administrator(s).");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to send daily job report: {$e->getMessage()}");
            Log::error('Daily job report command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Enhance job data with additional context.
     */
    protected function enhanceJobData(array $jobData, Carbon $targetDate): array
    {
        // Add hourly breakdown
        $jobData['hourly_breakdown'] = $this->generateHourlyBreakdown($targetDate);

        // Add top users data
        $jobData['top_users'] = $this->getTopUsers($targetDate);

        // Add failure reasons if any failures occurred
        if (($jobData['jobs']['failed_jobs'] ?? 0) > 0) {
            $jobData['failure_reasons'] = $this->getFailureReasons($targetDate);
        }

        return $jobData;
    }

    /**
     * Generate hourly breakdown for visualization.
     */
    protected function generateHourlyBreakdown(Carbon $date): array
    {
        $breakdown = [];

        // For demonstration, create a sample breakdown
        // In real implementation, this would query actual data
        for ($hour = 0; $hour < 24; $hour++) {
            $totalJobs = rand(0, 15);
            $failedJobs = $totalJobs > 0 ? rand(0, max(1, intval($totalJobs * 0.1))) : 0;

            if ($totalJobs > 0) {
                $breakdown[sprintf('%02d', $hour)] = [
                    'total' => $totalJobs,
                    'completed' => $totalJobs - $failedJobs,
                    'failed' => $failedJobs,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get top users by activity.
     */
    protected function getTopUsers(Carbon $date): array
    {
        // Placeholder implementation
        // In real implementation, this would query user activity data
        return [
            [
                'name' => 'Usuario Activo 1',
                'email' => 'user1@example.com',
                'jobs_count' => 5,
                'lines_processed' => 1250,
            ],
            [
                'name' => 'Usuario Activo 2',
                'email' => 'user2@example.com',
                'jobs_count' => 3,
                'lines_processed' => 890,
            ],
        ];
    }

    /**
     * Get failure reasons breakdown.
     */
    protected function getFailureReasons(Carbon $date): array
    {
        // Placeholder implementation
        return [
            'Invalid CSV format' => 2,
            'File too large' => 1,
            'Missing required columns' => 1,
        ];
    }

    /**
     * Determine if the report should be sent based on activity.
     */
    protected function shouldSendReport(array $jobData): bool
    {
        $totalJobs = $jobData['jobs']['total_jobs'] ?? 0;

        // Always send if there were failures
        if (($jobData['jobs']['failed_jobs'] ?? 0) > 0) {
            return true;
        }

        // Send if there was any meaningful activity
        if ($totalJobs > 0) {
            return true;
        }

        // Skip report for days with zero activity
        return false;
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
