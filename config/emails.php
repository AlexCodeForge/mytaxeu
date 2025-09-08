<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which email features are enabled in the application.
    | Useful for gradually rolling out features or disabling them during maintenance.
    |
    */
    'features' => [
        'subscription_emails' => env('EMAIL_FEATURE_SUBSCRIPTION_EMAILS', true),
        'file_processing_emails' => env('EMAIL_FEATURE_FILE_PROCESSING_EMAILS', true),
        'admin_notifications' => env('EMAIL_FEATURE_ADMIN_NOTIFICATIONS', true),
        'weekly_reports' => env('EMAIL_FEATURE_WEEKLY_REPORTS', true),
        'monthly_reports' => env('EMAIL_FEATURE_MONTHLY_REPORTS', true),
        'daily_reports' => env('EMAIL_FEATURE_DAILY_REPORTS', true),
        'operational_alerts' => env('EMAIL_FEATURE_FAILED_JOB_ALERTS', true),
        'bulk_emails' => env('EMAIL_FEATURE_BULK_EMAILS', true),
        'email_testing' => env('EMAIL_FEATURE_TESTING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Email Addresses
    |--------------------------------------------------------------------------
    |
    | Email addresses that will receive administrative notifications,
    | reports, and system alerts.
    |
    */
    'admin_addresses' => [
        env('ADMIN_EMAIL_PRIMARY', 'admin@mytaxeu.com'),
        env('ADMIN_EMAIL_SECONDARY'),
        env('ADMIN_EMAIL_TECHNICAL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Templates Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for email templates and rendering options.
    |
    */
    'templates' => [
        'default_layout' => 'emails.layouts.mytaxeu',
        'test_layout' => 'emails.layouts.mytaxeu',

        // Template-specific settings
        'user_notifications' => [
            'layout' => 'emails.layouts.mytaxeu',
            'unsubscribe_enabled' => true,
            'tracking_enabled' => true,
        ],

        'admin_notifications' => [
            'layout' => 'emails.layouts.mytaxeu',
            'unsubscribe_enabled' => false,
            'tracking_enabled' => true,
            'priority' => 'high',
        ],

        'reports' => [
            'layout' => 'emails.layouts.mytaxeu',
            'unsubscribe_enabled' => true,
            'tracking_enabled' => true,
            'attachments_enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Email queue settings for background processing.
    |
    */
    'queue' => [
        'default_queue' => env('EMAIL_DEFAULT_QUEUE', 'emails'),
        'bulk_queue' => env('EMAIL_BULK_QUEUE', 'bulk-emails'),
        'priority_queue' => env('EMAIL_PRIORITY_QUEUE', 'priority-emails'),
        'report_queue' => env('EMAIL_REPORT_QUEUE', 'report-emails'),

        // Retry configuration
        'max_retries' => env('EMAIL_MAX_RETRIES', 3),
        'retry_delay' => env('EMAIL_RETRY_DELAY', 300), // seconds

        // Bulk email settings
        'bulk_batch_size' => env('EMAIL_BULK_BATCH_SIZE', 50),
        'bulk_delay_between_batches' => env('EMAIL_BULK_DELAY', 5), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Email sending rate limits to prevent abuse and ensure deliverability.
    |
    */
    'rate_limits' => [
        'per_user_per_hour' => env('EMAIL_RATE_LIMIT_USER_HOUR', 10),
        'per_user_per_day' => env('EMAIL_RATE_LIMIT_USER_DAY', 50),
        'system_per_hour' => env('EMAIL_RATE_LIMIT_SYSTEM_HOUR', 1000),
        'system_per_day' => env('EMAIL_RATE_LIMIT_SYSTEM_DAY', 10000),

        // Admin emails (no limits by default)
        'admin_per_hour' => env('EMAIL_RATE_LIMIT_ADMIN_HOUR', null),
        'admin_per_day' => env('EMAIL_RATE_LIMIT_ADMIN_DAY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for specific notification types and their behavior.
    |
    */
    'notifications' => [
        // User Notifications
        'subscription_payment_confirmation' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.subscription-payment-confirmation',
        ],

        'subscription_renewal_reminder' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.subscription-renewal-reminder',
            'send_days_before' => 7,
        ],

        'file_upload_confirmation' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.file-upload-confirmation',
        ],

        'file_processing_started' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.file-processing-started',
        ],

        'file_processing_completed' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.file-processing-completed',
        ],

        'file_processing_failed' => [
            'enabled' => true,
            'queue' => 'emails',
            'delay' => 0,
            'template' => 'emails.users.file-processing-failed',
        ],

        // Admin Notifications
        'sale_notification' => [
            'enabled' => true,
            'queue' => 'priority-emails',
            'delay' => 0,
            'template' => 'emails.admin.sale-notification',
        ],

        'daily_job_status_report' => [
            'enabled' => true,
            'queue' => 'report-emails',
            'delay' => 0,
            'template' => 'emails.admin.daily-job-status-report',
            'schedule' => '0 8 * * *', // Daily at 8 AM
        ],

        'weekly_sales_report' => [
            'enabled' => true,
            'queue' => 'report-emails',
            'delay' => 0,
            'template' => 'emails.admin.weekly-sales-report',
            'schedule' => '0 9 * * 1', // Mondays at 9 AM
        ],

        'monthly_sales_report' => [
            'enabled' => true,
            'queue' => 'report-emails',
            'delay' => 0,
            'template' => 'emails.admin.monthly-sales-report',
            'schedule' => '0 9 1 * *', // 1st of month at 9 AM
        ],

        'failed_job_alert' => [
            'enabled' => true,
            'queue' => 'priority-emails',
            'delay' => 0,
            'template' => 'emails.admin.failed-job-alert',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for email testing and development.
    |
    */
    'testing' => [
        'test_email' => env('EMAIL_TEST_ADDRESS', 'test@mytaxeu.com'),
        'generate_real_data' => env('EMAIL_TEST_REAL_DATA', false),
        'save_templates_to_disk' => env('EMAIL_TEST_SAVE_TEMPLATES', false),
        'template_save_path' => storage_path('emails/test-renders'),

        // Mock data for testing
        'mock_data' => [
            'user' => [
                'name' => 'Juan Pérez',
                'email' => 'test@example.com',
                'subscription_plan' => 'Plan Business',
            ],
            'subscription' => [
                'amount' => 125.00,
                'currency' => 'EUR',
                'credits' => 500,
                'next_billing_date' => '2024-02-25',
            ],
            'file' => [
                'name' => 'amazon_report_enero_2024.csv',
                'size' => '2.3 MB',
                'rows' => 1250,
                'processing_time' => '3 minutes',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Analytics
    |--------------------------------------------------------------------------
    |
    | Email monitoring and analytics configuration.
    |
    */
    'monitoring' => [
        'track_opens' => env('EMAIL_TRACK_OPENS', true),
        'track_clicks' => env('EMAIL_TRACK_CLICKS', true),
        'track_bounces' => env('EMAIL_TRACK_BOUNCES', true),
        'track_complaints' => env('EMAIL_TRACK_COMPLAINTS', true),

        // Alerting thresholds
        'alert_failure_rate' => env('EMAIL_ALERT_FAILURE_RATE', 5.0), // percentage
        'alert_bounce_rate' => env('EMAIL_ALERT_BOUNCE_RATE', 10.0), // percentage
        'alert_complaint_rate' => env('EMAIL_ALERT_COMPLAINT_RATE', 0.5), // percentage

        // Retention settings
        'keep_logs_days' => env('EMAIL_KEEP_LOGS_DAYS', 90),
        'keep_analytics_days' => env('EMAIL_KEEP_ANALYTICS_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Configuration
    |--------------------------------------------------------------------------
    |
    | Default content and copy for emails.
    |
    */
    'content' => [
        'company_name' => 'MyTaxEU',
        'company_tagline' => 'Automatización Fiscal Inteligente para Amazon',
        'support_email' => env('SUPPORT_EMAIL', 'soporte@mytaxeu.com'),
        'no_reply_email' => env('NO_REPLY_EMAIL', 'noreply@mytaxeu.com'),

        'footer_links' => [
            'dashboard' => '/dashboard',
            'support' => '/support',
            'privacy' => '/privacy',
            'terms' => '/terms',
        ],

        'social_links' => [
            'twitter' => env('SOCIAL_TWITTER', ''),
            'linkedin' => env('SOCIAL_LINKEDIN', ''),
            'facebook' => env('SOCIAL_FACEBOOK', ''),
        ],

        'unsubscribe' => [
            'enabled' => true,
            'url' => '/unsubscribe',
            'text' => 'Si no deseas recibir estos emails, puedes darte de baja aquí.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Email security and validation settings.
    |
    */
    'security' => [
        'validate_email_addresses' => true,
        'sanitize_content' => true,
        'encrypt_sensitive_data' => true,
        'require_ssl' => env('EMAIL_REQUIRE_SSL', true),

        // Blocked domains (spam protection)
        'blocked_domains' => [
            'tempmail.org',
            '10minutemail.com',
            'guerrillamail.com',
        ],

        // Allowed admin domains
        'admin_domains' => [
            'mytaxeu.com',
        ],
    ],
];
