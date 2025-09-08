<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSetting;
use Illuminate\Support\Facades\Config;

class EmailConfigService
{
    /**
     * Get email feature status.
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        $key = match ($feature) {
            'subscription_emails' => 'subscription_emails_enabled',
            'file_processing_emails' => 'file_processing_emails_enabled',
            'admin_notifications' => 'admin_notifications_enabled',
            'weekly_reports' => 'weekly_reports_enabled',
            'monthly_reports' => 'monthly_reports_enabled',
            'daily_reports' => 'daily_reports_enabled',
            'operational_alerts' => 'operational_alerts_enabled',
            default => $feature . '_enabled',
        };

        return EmailSetting::getValue($key, Config::get("emails.features.{$feature}", true));
    }

    /**
     * Get admin email addresses.
     */
    public static function getAdminEmails(): array
    {
        $primary = EmailSetting::getValue('admin_email_primary', 'admin@mytaxeu.com');
        $secondary = EmailSetting::getValue('admin_email_secondary');

        $emails = array_filter([$primary, $secondary]);

        // Fallback to config if no database settings
        if (empty($emails)) {
            return array_filter(Config::get('emails.admin_addresses', ['admin@mytaxeu.com']));
        }

        return $emails;
    }

    /**
     * Get notification settings for a specific type.
     */
    public static function getNotificationConfig(string $type): array
    {
        $baseConfig = Config::get("emails.notifications.{$type}", []);

        return [
            'enabled' => EmailSetting::getValue("{$type}_enabled", $baseConfig['enabled'] ?? true),
            'queue' => EmailSetting::getValue("{$type}_queue", $baseConfig['queue'] ?? 'emails'),
            'delay' => (int) EmailSetting::getValue("{$type}_delay", $baseConfig['delay'] ?? 0),
            'template' => EmailSetting::getValue("{$type}_template", $baseConfig['template'] ?? ''),
        ];
    }

    /**
     * Get schedule settings.
     */
    public static function getScheduleConfig(): array
    {
        return [
            'daily_report_time' => EmailSetting::getValue('daily_report_time', '08:00'),
            'weekly_report_time' => EmailSetting::getValue('weekly_report_time', '09:00'),
            'monthly_report_time' => EmailSetting::getValue('monthly_report_time', '09:00'),
            'renewal_reminder_days' => (int) EmailSetting::getValue('renewal_reminder_days', 7),
        ];
    }

    /**
     * Get general email settings.
     */
    public static function getGeneralConfig(): array
    {
        return [
            'sender_name' => EmailSetting::getValue('sender_name', Config::get('mail.from.name', 'MyTaxEU')),
            'support_email' => EmailSetting::getValue('support_email', 'support@mytaxeu.com'),
            'admin_email_primary' => EmailSetting::getValue('admin_email_primary', 'admin@mytaxeu.com'),
        ];
    }

    /**
     * Check if a specific notification type is enabled.
     */
    public static function isNotificationEnabled(string $type): bool
    {
        return EmailSetting::getValue("{$type}_enabled", true);
    }

    /**
     * Get queue name for a notification type.
     */
    public static function getNotificationQueue(string $type): string
    {
        $configQueue = Config::get("emails.notifications.{$type}.queue", 'emails');
        return EmailSetting::getValue("{$type}_queue", $configQueue);
    }

    /**
     * Get delay for a notification type.
     */
    public static function getNotificationDelay(string $type): int
    {
        $configDelay = Config::get("emails.notifications.{$type}.delay", 0);
        return (int) EmailSetting::getValue("{$type}_delay", $configDelay);
    }

    /**
     * Get template for a notification type.
     */
    public static function getNotificationTemplate(string $type): string
    {
        $configTemplate = Config::get("emails.notifications.{$type}.template", '');
        return EmailSetting::getValue("{$type}_template", $configTemplate);
    }

    /**
     * Get all email settings grouped by category for admin panel.
     */
    public static function getAllSettingsForAdmin(): array
    {
        return EmailSetting::getAllGrouped();
    }

    /**
     * Update email setting value.
     */
    public static function updateSetting(string $key, $value): bool
    {
        return EmailSetting::setValue($key, $value);
    }

    /**
     * Get cron schedule for a report type.
     */
    public static function getReportSchedule(string $reportType): string
    {
        $time = match ($reportType) {
            'daily' => EmailSetting::getValue('daily_report_time', '08:00'),
            'weekly' => EmailSetting::getValue('weekly_report_time', '09:00'),
            'monthly' => EmailSetting::getValue('monthly_report_time', '09:00'),
            default => '09:00',
        };

        // Convert HH:MM to cron format
        [$hour, $minute] = explode(':', $time);

        return match ($reportType) {
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => "{$minute} {$hour} * * 1", // Mondays
            'monthly' => "{$minute} {$hour} 1 * *", // 1st of month
            default => "0 {$hour} * * *",
        };
    }

    /**
     * Check if admin notifications are globally enabled.
     */
    public static function areAdminNotificationsEnabled(): bool
    {
        return self::isFeatureEnabled('admin_notifications');
    }

    /**
     * Check if user notifications are globally enabled.
     */
    public static function areUserNotificationsEnabled(): bool
    {
        return self::isFeatureEnabled('subscription_emails') || self::isFeatureEnabled('file_processing_emails');
    }
}

