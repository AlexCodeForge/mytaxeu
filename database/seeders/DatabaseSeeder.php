<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminActionLog;
use App\Models\AdminSetting;
use App\Models\CreditTransaction;
use App\Models\IpUploadTracking;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use App\Models\UserUploadLimit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive testing data seed...');

        DB::transaction(function () {
            // Create admin settings first
            $this->createAdminSettings();

            // Create admin users (including your existing one)
            $adminUsers = $this->createAdminUsers();

            // Create regular users with different profiles
            $users = $this->createRegularUsers();

            // Combine all users
            $allUsers = $adminUsers->merge($users);

            // Create subscriptions for users
            $this->createSubscriptions($users);

            // Create uploads and related data for each user
            $this->createUploadsAndRelatedData($allUsers);

            // Create IP tracking data
            $this->createIpTrackingData();

            // Create admin action logs
            $this->createAdminActionLogs($adminUsers, $allUsers);

            // Create user upload limits
            $this->createUserUploadLimits($adminUsers, $users);
        });

        $this->command->info('✅ Testing data seeding completed successfully!');
        $this->displaySummary();

        // Optionally call other seeders
        // $this->call([
        //     JobStatusTestDataSeeder::class,
        // ]);
    }

    /**
     * Create admin settings for the application.
     */
    protected function createAdminSettings(): void
    {
        $this->command->info('Creating admin settings...');

        // Stripe configuration
        AdminSetting::factory()->stripeConfig()->create([
            'key' => 'stripe_public_key',
            'value' => 'pk_test_' . fake()->regexify('[A-Za-z0-9]{50}'),
            'description' => 'Stripe Publishable Key for testing',
        ]);

        AdminSetting::factory()->stripeConfig()->create([
            'key' => 'stripe_secret_key',
            'value' => 'sk_test_' . fake()->regexify('[A-Za-z0-9]{50}'),
            'encrypted' => true,
            'description' => 'Stripe Secret Key (Encrypted)',
        ]);

        AdminSetting::factory()->stripeConfig()->create([
            'key' => 'stripe_webhook_secret',
            'value' => 'whsec_' . fake()->regexify('[A-Za-z0-9]{50}'),
            'encrypted' => true,
            'description' => 'Stripe Webhook Secret (Encrypted)',
        ]);

        AdminSetting::factory()->stripeConfig()->create([
            'key' => 'stripe_test_mode',
            'value' => '1',
            'description' => 'Stripe Test Mode Enabled',
        ]);

        // System settings
        AdminSetting::factory()->systemSetting()->createMany([
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'description' => 'Maintenance mode status',
            ],
            [
                'key' => 'max_upload_size',
                'value' => '10485760', // 10MB
                'description' => 'Maximum upload file size in bytes',
            ],
            [
                'key' => 'default_credits',
                'value' => '1000',
                'description' => 'Default credits for new users',
            ],
            [
                'key' => 'credit_rate',
                'value' => '0.01',
                'description' => 'Cost per line processed in credits',
            ],
        ]);
    }

    /**
     * Create admin users.
     */
    protected function createAdminUsers()
    {
        $this->command->info('Creating admin users...');

        return collect([
            // Your existing admin user (preserved exactly as you want)
            User::factory()->admin()->create([
                'name' => 'Admin User',
                'email' => 'axldeth@gmail.com',
                'password' => 'password',
                'is_admin' => true,
                'credits' => 50000,
                'total_lines_processed' => 100000,
            ]),

            // Additional admin users for testing
            User::factory()->admin()->create([
                'name' => 'Super Admin',
                'email' => 'super@admin.com',
                'credits' => 75000,
                'total_lines_processed' => 150000,
            ]),

            User::factory()->admin()->create([
                'name' => 'Technical Admin',
                'email' => 'tech@admin.com',
                'credits' => 25000,
                'total_lines_processed' => 50000,
            ]),
        ]);
    }

    /**
     * Create regular users with different profiles.
     */
    protected function createRegularUsers()
    {
        $this->command->info('Creating regular users...');

        $users = collect();

        // High-activity users (3 users)
        $users = $users->merge(
            User::factory(3)->withHighCredits()->withHighUsage()->create()
        );

        // Medium-activity users (4 users)
        $users = $users->merge(
            User::factory(4)->create([
                'credits' => fake()->numberBetween(2000, 8000),
                'total_lines_processed' => fake()->numberBetween(10000, 30000),
                'current_month_usage' => fake()->numberBetween(1000, 3000),
            ])
        );

        // Low-activity users (3 users)
        $users = $users->merge(
            User::factory(3)->create([
                'credits' => fake()->numberBetween(100, 1000),
                'total_lines_processed' => fake()->numberBetween(100, 5000),
                'current_month_usage' => fake()->numberBetween(50, 500),
            ])
        );

        // New users with no credits (2 users)
        $users = $users->merge(
            User::factory(2)->withNoCredits()->create([
                'total_lines_processed' => 0,
                'current_month_usage' => 0,
            ])
        );

        return $users;
    }

    /**
     * Create subscriptions for users.
     */
    protected function createSubscriptions($users): void
    {
        $this->command->info('Creating subscriptions...');

        $subscriptionTypes = ['basic', 'premium', 'enterprise'];
        $statusTypes = ['active', 'trialing', 'canceled', 'pastDue'];

        $users->each(function ($user, $index) use ($subscriptionTypes, $statusTypes) {
            // Not all users have subscriptions
            if ($index % 3 === 0) {
                return; // Skip every 3rd user
            }

            $subscriptionType = $subscriptionTypes[$index % count($subscriptionTypes)];
            $statusType = $statusTypes[$index % count($statusTypes)];

            $subscription = Subscription::factory()
                ->{$subscriptionType}()
                ->{$statusType}()
                ->create(['user_id' => $user->id]);

            // Create subscription items
            SubscriptionItem::factory()
                ->{$subscriptionType . 'Plan'}()
                ->create(['subscription_id' => $subscription->id]);

            // Create credit transactions for subscription with varied dates
            if (in_array($statusType, ['active', 'trialing'])) {
                $subscriptionDate = fake()->dateTimeBetween('-3 months', '-1 month');

                CreditTransaction::factory()->purchase()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'amount' => match($subscriptionType) {
                        'basic' => fake()->numberBetween(1000, 2000),
                        'premium' => fake()->numberBetween(2000, 5000),
                        'enterprise' => fake()->numberBetween(5000, 10000),
                        default => 1000,
                    },
                    'description' => ucfirst($subscriptionType) . ' subscription payment',
                    'created_at' => $subscriptionDate,
                    'updated_at' => $subscriptionDate,
                ]);
            }
        });
    }

    /**
     * Create uploads and related data for each user.
     */
    protected function createUploadsAndRelatedData($users): void
    {
        $this->command->info('Creating uploads and related data...');

        $users->each(function ($user) {
            // Create 20 uploads per user as requested with varied dates over the last 6 months
            $uploads = collect();

            for ($i = 0; $i < 20; $i++) {
                // Spread uploads across the last 6 months
                $createdAt = fake()->dateTimeBetween('-6 months', 'now');

                $upload = Upload::factory()->create([
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $uploads->push($upload);
            }

            $uploads->each(function ($upload, $index) use ($user) {
                // Vary the upload statuses using model constants
                $statusOptions = [
                    Upload::STATUS_COMPLETED, Upload::STATUS_COMPLETED, Upload::STATUS_COMPLETED, Upload::STATUS_COMPLETED,
                    Upload::STATUS_PROCESSING, Upload::STATUS_PROCESSING,
                    Upload::STATUS_FAILED,
                    Upload::STATUS_COMPLETED, Upload::STATUS_COMPLETED, Upload::STATUS_COMPLETED
                ];
                $status = $statusOptions[$index % 10];

                // Update upload with appropriate status
                $upload->update(['status' => $status]);

                // Calculate realistic processing dates based on upload creation
                $uploadCreatedAt = $upload->created_at;
                $processingTime = fake()->numberBetween(5, 120); // 5 minutes to 2 hours processing time
                $processedAt = $uploadCreatedAt->addMinutes($processingTime);

                if ($status === Upload::STATUS_COMPLETED) {
                    $upload->update([
                        'status' => Upload::STATUS_COMPLETED,
                        'processed_at' => $processedAt,
                        'transformed_path' => 'uploads/' . $user->id . '/output/test_' . fake()->uuid() . '_transformado.csv',
                        'credits_consumed' => fake()->numberBetween(10, 500),
                        'detected_periods' => fake()->randomElements(['2023', '2024', '2025'], rand(1, 3)),
                        'notification_sent_at' => $processedAt->addMinutes(fake()->numberBetween(1, 30)),
                        'notification_type' => 'success',
                    ]);
                } elseif ($status === Upload::STATUS_FAILED) {
                    $upload->update([
                        'status' => Upload::STATUS_FAILED,
                        'failure_reason' => fake()->sentence(),
                        'processed_at' => $processedAt,
                        'credits_consumed' => 0,
                        'notification_sent_at' => $processedAt->addMinutes(fake()->numberBetween(1, 10)),
                        'notification_type' => 'failure',
                    ]);
                } elseif ($status === Upload::STATUS_PROCESSING) {
                    $upload->update([
                        'status' => Upload::STATUS_PROCESSING,
                        'credits_consumed' => 0,
                        'notification_type' => 'processing',
                    ]);
                }

                // Create upload metrics with realistic timestamps
                $metricData = [
                    'user_id' => $user->id,
                    'upload_id' => $upload->id,
                    'file_name' => $upload->original_name,
                    'file_size_bytes' => $upload->size_bytes,
                    'line_count' => $upload->csv_line_count,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                ];

                if ($status === Upload::STATUS_COMPLETED) {
                    $metricData['processing_started_at'] = $upload->created_at->addMinutes(fake()->numberBetween(1, 5));
                    $metricData['processing_completed_at'] = $upload->processed_at;
                    $metricData['processing_duration_seconds'] = $metricData['processing_started_at']->diffInSeconds($metricData['processing_completed_at']);
                    $metricData['status'] = 'completed';
                    $metricData['credits_consumed'] = $upload->credits_consumed;
                } elseif ($status === Upload::STATUS_FAILED) {
                    $metricData['processing_started_at'] = $upload->created_at->addMinutes(fake()->numberBetween(1, 5));
                    $metricData['processing_completed_at'] = $upload->processed_at;
                    $metricData['processing_duration_seconds'] = $metricData['processing_started_at']->diffInSeconds($metricData['processing_completed_at']);
                    $metricData['status'] = 'failed';
                    $metricData['error_message'] = $upload->failure_reason;
                    $metricData['credits_consumed'] = 0;
                } elseif ($status === Upload::STATUS_PROCESSING) {
                    $metricData['processing_started_at'] = $upload->created_at->addMinutes(fake()->numberBetween(1, 5));
                    $metricData['status'] = 'processing';
                    $metricData['credits_consumed'] = 0;
                }

                UploadMetric::factory()->create($metricData);

                // Create credit transactions for completed uploads
                if ($status === Upload::STATUS_COMPLETED && $upload->credits_consumed) {
                    CreditTransaction::factory()->usage()->create([
                        'user_id' => $user->id,
                        'upload_id' => $upload->id,
                        'amount' => -$upload->credits_consumed,
                        'description' => 'CSV processing: ' . $upload->original_name,
                        'created_at' => $upload->processed_at->addMinutes(fake()->numberBetween(1, 5)),
                        'updated_at' => $upload->processed_at->addMinutes(fake()->numberBetween(1, 5)),
                    ]);
                }
            });

            // Create additional credit transactions (purchases, refunds, etc.) with varied dates
            $transactionCount = fake()->numberBetween(2, 8);
            for ($i = 0; $i < $transactionCount; $i++) {
                $transactionDate = fake()->dateTimeBetween('-6 months', 'now');
                $transactionType = fake()->randomElement(['purchased', 'refunded']);

                CreditTransaction::factory()->create([
                    'user_id' => $user->id,
                    'type' => $transactionType,
                    'amount' => $transactionType === 'purchased'
                        ? fake()->numberBetween(500, 5000)
                        : -fake()->numberBetween(100, 2000),
                    'description' => $transactionType === 'purchased'
                        ? fake()->randomElement([
                            'Credit purchase',
                            'Monthly subscription payment',
                            'One-time credit purchase',
                            'Subscription renewal',
                        ])
                        : fake()->randomElement([
                            'Refund processed',
                            'Partial refund',
                            'Customer service refund',
                        ]),
                    'created_at' => $transactionDate,
                    'updated_at' => $transactionDate,
                ]);
            }
        });
    }

    /**
     * Create IP tracking data.
     */
    protected function createIpTrackingData(): void
    {
        $this->command->info('Creating IP tracking data...');

        // High activity IPs with varied creation dates
        for ($i = 0; $i < 10; $i++) {
            $trackingDate = fake()->dateTimeBetween('-6 months', 'now');
            IpUploadTracking::factory()->highActivity()->create([
                'created_at' => $trackingDate,
                'updated_at' => $trackingDate,
                'last_upload_at' => fake()->dateTimeBetween($trackingDate, 'now'),
            ]);
        }

        // Recent activity IPs
        for ($i = 0; $i < 15; $i++) {
            $trackingDate = fake()->dateTimeBetween('-1 month', 'now');
            IpUploadTracking::factory()->recentActivity()->create([
                'created_at' => $trackingDate,
                'updated_at' => $trackingDate,
                'last_upload_at' => fake()->dateTimeBetween($trackingDate, 'now'),
            ]);
        }

        // Old activity IPs
        for ($i = 0; $i < 25; $i++) {
            $trackingDate = fake()->dateTimeBetween('-6 months', '-2 months');
            IpUploadTracking::factory()->oldActivity()->create([
                'created_at' => $trackingDate,
                'updated_at' => $trackingDate,
                'last_upload_at' => fake()->dateTimeBetween($trackingDate, '-1 month'),
            ]);
        }

        // Regular activity IPs
        for ($i = 0; $i < 50; $i++) {
            $trackingDate = fake()->dateTimeBetween('-4 months', 'now');
            IpUploadTracking::factory()->create([
                'created_at' => $trackingDate,
                'updated_at' => $trackingDate,
                'last_upload_at' => fake()->dateTimeBetween($trackingDate, 'now'),
            ]);
        }
    }

    /**
     * Create admin action logs.
     */
    protected function createAdminActionLogs($adminUsers, $allUsers): void
    {
        $this->command->info('Creating admin action logs...');

        $adminUsers->each(function ($admin) use ($allUsers) {
            // Create various admin actions with varied dates over the last 3 months

            // Limit override actions
            $limitOverrideCount = fake()->numberBetween(3, 8);
            for ($i = 0; $i < $limitOverrideCount; $i++) {
                $actionDate = fake()->dateTimeBetween('-3 months', 'now');
                AdminActionLog::factory()
                    ->limitOverride()
                    ->create([
                        'admin_user_id' => $admin->id,
                        'target_user_id' => $allUsers->random()->id,
                        'created_at' => $actionDate,
                        'updated_at' => $actionDate,
                    ]);
            }

            // Limit reset actions
            $limitResetCount = fake()->numberBetween(2, 6);
            for ($i = 0; $i < $limitResetCount; $i++) {
                $actionDate = fake()->dateTimeBetween('-3 months', 'now');
                AdminActionLog::factory()
                    ->limitReset()
                    ->create([
                        'admin_user_id' => $admin->id,
                        'target_user_id' => $allUsers->random()->id,
                        'created_at' => $actionDate,
                        'updated_at' => $actionDate,
                    ]);
            }

            // Usage reset actions
            $usageResetCount = fake()->numberBetween(2, 5);
            for ($i = 0; $i < $usageResetCount; $i++) {
                $actionDate = fake()->dateTimeBetween('-3 months', 'now');
                AdminActionLog::factory()
                    ->usageReset()
                    ->create([
                        'admin_user_id' => $admin->id,
                        'target_user_id' => $allUsers->random()->id,
                        'created_at' => $actionDate,
                        'updated_at' => $actionDate,
                    ]);
            }

            // General admin actions
            $generalActionsCount = fake()->numberBetween(3, 8);
            for ($i = 0; $i < $generalActionsCount; $i++) {
                $actionDate = fake()->dateTimeBetween('-3 months', 'now');
                AdminActionLog::factory()
                    ->create([
                        'admin_user_id' => $admin->id,
                        'target_user_id' => $allUsers->random()->id,
                        'created_at' => $actionDate,
                        'updated_at' => $actionDate,
                    ]);
            }
        });
    }

    /**
     * Create user upload limits.
     */
    protected function createUserUploadLimits($adminUsers, $users): void
    {
        $this->command->info('Creating user upload limits...');

        // Give some users special upload limits
        $usersWithLimits = $users->random(fake()->numberBetween(3, 8));

        $usersWithLimits->each(function ($user) use ($adminUsers) {
            $limitType = fake()->randomElement(['highLimit', 'lowLimit', 'permanent', 'expired']);

            UserUploadLimit::factory()
                ->{$limitType}()
                ->create([
                    'user_id' => $user->id,
                    'created_by' => $adminUsers->random()->id,
                ]);
        });
    }

    /**
     * Display a summary of created data.
     */
    protected function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 SEEDING SUMMARY:');
        $this->command->line('─────────────────────');

        $counts = [
            'Users' => User::count(),
            'Admins' => User::where('is_admin', true)->count(),
            'Subscriptions' => Subscription::count(),
            'Subscription Items' => SubscriptionItem::count(),
            'Uploads' => Upload::count(),
            'Upload Metrics' => UploadMetric::count(),
            'Credit Transactions' => CreditTransaction::count(),
            'Admin Action Logs' => AdminActionLog::count(),
            'IP Tracking Records' => IpUploadTracking::count(),
            'User Upload Limits' => UserUploadLimit::count(),
            'Admin Settings' => AdminSetting::count(),
        ];

        foreach ($counts as $model => $count) {
            $this->command->line("• {$model}: {$count}");
        }

        $this->command->newLine();
        $this->command->info('🎯 Test Data Features:');
        $this->command->line('• 15+ diverse users with different usage patterns');
        $this->command->line('• 300+ upload records (20 per user) with various statuses');
        $this->command->line('• Realistic date spread over 6 months for uploads and transactions');
        $this->command->line('• Multiple subscription types and statuses');
        $this->command->line('• Comprehensive credit transaction history with varied timing');
        $this->command->line('• Admin activity logs spanning 3 months');
        $this->command->line('• IP-based upload tracking with different activity periods');
        $this->command->line('• Custom upload limits for some users');
        $this->command->line('• Complete admin settings configuration');

        $this->command->newLine();
        $this->command->info('🔑 Test Accounts:');
        $this->command->line('• Main Admin: axldeth@gmail.com (password: password)');
        $this->command->line('• Super Admin: super@admin.com (password: password)');
        $this->command->line('• Technical Admin: tech@admin.com (password: password)');
        $this->command->line('• Regular users: Check users table for test accounts');
    }
}
