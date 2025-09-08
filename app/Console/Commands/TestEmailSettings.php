<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmailSetting;
use App\Services\EmailConfigService;
use Illuminate\Console\Command;

class TestEmailSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-settings
                            {--show-all : Show all email settings}
                            {--test-config : Test EmailConfigService functionality}
                            {--check-notifications : Check notification configurations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email settings functionality and database configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Email Settings System');
        $this->line('');

        // Test database connectivity
        try {
            $count = EmailSetting::count();
            $this->info("✅ Database connectivity: {$count} email settings found");
        } catch (\Exception $e) {
            $this->error("❌ Database error: " . $e->getMessage());
            return 1;
        }

        if ($this->option('show-all')) {
            $this->showAllSettings();
        }

        if ($this->option('test-config')) {
            $this->testEmailConfigService();
        }

        if ($this->option('check-notifications')) {
            $this->checkNotifications();
        }

        if (!$this->hasOption('show-all') && !$this->hasOption('test-config') && !$this->hasOption('check-notifications')) {
            $this->basicTests();
        }

        $this->line('');
        $this->info('🎉 Email settings test completed!');
        return 0;
    }

    private function basicTests()
    {
        $this->line('');
        $this->info('📊 Basic Configuration Tests:');

        // Test feature flags
        $features = [
            'subscription_emails' => EmailConfigService::isFeatureEnabled('subscription_emails'),
            'file_processing_emails' => EmailConfigService::isFeatureEnabled('file_processing_emails'),
            'admin_notifications' => EmailConfigService::isFeatureEnabled('admin_notifications'),
            'weekly_reports' => EmailConfigService::isFeatureEnabled('weekly_reports'),
            'monthly_reports' => EmailConfigService::isFeatureEnabled('monthly_reports'),
        ];

        foreach ($features as $feature => $enabled) {
            $status = $enabled ? '✅ Enabled' : '❌ Disabled';
            $this->line("  {$feature}: {$status}");
        }

        // Test admin emails
        $this->line('');
        $this->info('📧 Admin Email Configuration:');
        $adminEmails = EmailConfigService::getAdminEmails();
        foreach ($adminEmails as $email) {
            $this->line("  • {$email}");
        }

        // Test general settings
        $this->line('');
        $this->info('⚙️ General Settings:');
        $general = EmailConfigService::getGeneralConfig();
        foreach ($general as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
    }

    private function showAllSettings()
    {
        $this->line('');
        $this->info('📋 All Email Settings:');

        $groupedSettings = EmailSetting::getAllGrouped();

        foreach ($groupedSettings as $category => $settings) {
            $this->line('');
            $this->comment("🏷️  Category: " . ucwords(str_replace('_', ' ', $category)));

            foreach ($settings as $setting) {
                $value = $setting['value'];
                if ($setting['type'] === 'boolean') {
                    $value = $value === '1' ? '✅ True' : '❌ False';
                }
                $this->line("  • {$setting['label']}: {$value}");
            }
        }
    }

    private function testEmailConfigService()
    {
        $this->line('');
        $this->info('🔧 Testing EmailConfigService Methods:');

        // Test notification configs
        $notifications = [
            'subscription_payment_confirmation',
            'sale_notification',
            'weekly_sales_report',
            'monthly_sales_report',
            'failed_job_alert'
        ];

        foreach ($notifications as $notification) {
            $config = EmailConfigService::getNotificationConfig($notification);
            $enabled = $config['enabled'] ? '✅' : '❌';
            $this->line("  {$notification}: {$enabled} Queue: {$config['queue']} Delay: {$config['delay']}s");
        }

        // Test schedule configuration
        $this->line('');
        $this->comment('📅 Schedule Configuration:');
        $schedules = EmailConfigService::getScheduleConfig();
        foreach ($schedules as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
    }

    private function checkNotifications()
    {
        $this->line('');
        $this->info('📬 Notification Configuration Check:');

        $notifications = [
            'subscription_payment_confirmation' => 'Subscription Payment Confirmation',
            'subscription_renewal_reminder' => 'Subscription Renewal Reminder',
            'file_upload_confirmation' => 'File Upload Confirmation',
            'file_processing_started' => 'File Processing Started',
            'file_processing_completed' => 'File Processing Completed',
            'file_processing_failed' => 'File Processing Failed',
            'sale_notification' => 'Sale Notification',
            'weekly_sales_report' => 'Weekly Sales Report',
            'monthly_sales_report' => 'Monthly Sales Report',
            'daily_job_status_report' => 'Daily Job Status Report',
            'failed_job_alert' => 'Failed Job Alert',
        ];

        foreach ($notifications as $key => $name) {
            $enabled = EmailConfigService::isNotificationEnabled($key);
            $queue = EmailConfigService::getNotificationQueue($key);
            $template = EmailConfigService::getNotificationTemplate($key);

            $status = $enabled ? '✅ Enabled' : '❌ Disabled';
            $this->line("  {$name}:");
            $this->line("    Status: {$status}");
            $this->line("    Queue: {$queue}");
            $this->line("    Template: {$template}");
            $this->line('');
        }
    }
}
