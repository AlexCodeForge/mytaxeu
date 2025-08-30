<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobStatusTestDataSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create test users if they don't exist
        $user1 = User::firstOrCreate(
            ['email' => 'test1@example.com'],
            [
                'name' => 'Test User 1',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'credits' => 100,
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'test2@example.com'],
            [
                'name' => 'Test User 2',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'credits' => 50,
            ]
        );

        // Create test jobs with different statuses
        $jobs = [
            [
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'test-job-1', 'displayName' => 'ProcessUploadJob']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time() - 3600, // 1 hour ago
                'status' => 'completed',
                'started_at' => now()->subMinutes(55),
                'completed_at' => now()->subMinutes(50),
                'user_id' => $user1->id,
                'file_name' => 'test_file_1.csv',
                'error_message' => null,
            ],
            [
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'test-job-2', 'displayName' => 'ProcessUploadJob']),
                'attempts' => 1,
                'reserved_at' => time(),
                'available_at' => time(),
                'created_at' => time() - 1800, // 30 minutes ago
                'status' => 'processing',
                'started_at' => now()->subMinutes(25),
                'completed_at' => null,
                'user_id' => $user1->id,
                'file_name' => 'test_file_2.csv',
                'error_message' => null,
            ],
            [
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'test-job-3', 'displayName' => 'ProcessUploadJob']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time() - 600, // 10 minutes ago
                'status' => 'queued',
                'started_at' => null,
                'completed_at' => null,
                'user_id' => $user2->id,
                'file_name' => 'test_file_3.csv',
                'error_message' => null,
            ],
            [
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'test-job-4', 'displayName' => 'ProcessUploadJob']),
                'attempts' => 3,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time() - 7200, // 2 hours ago
                'status' => 'failed',
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subMinutes(90),
                'user_id' => $user2->id,
                'file_name' => 'test_file_4.csv',
                'error_message' => 'CSV validation failed: Invalid format',
            ],
        ];

        foreach ($jobs as $jobData) {
            $jobId = DB::table('jobs')->insertGetId($jobData);

            // Create corresponding job logs
            $logs = [
                [
                    'job_id' => $jobId,
                    'level' => 'info',
                    'message' => 'Job queued for processing',
                    'metadata' => json_encode(['file_size' => rand(1000, 50000)]),
                    'created_at' => now()->subMinutes(rand(30, 120)),
                ],
                [
                    'job_id' => $jobId,
                    'level' => $jobData['status'] === 'failed' ? 'error' : 'info',
                    'message' => $jobData['status'] === 'failed'
                        ? 'Processing failed: ' . $jobData['error_message']
                        : 'Processing started',
                    'metadata' => json_encode(['step' => $jobData['status']]),
                    'created_at' => now()->subMinutes(rand(5, 60)),
                ],
            ];

            if ($jobData['status'] === 'completed') {
                $logs[] = [
                    'job_id' => $jobId,
                    'level' => 'info',
                    'message' => 'Processing completed successfully',
                    'metadata' => json_encode(['rows_processed' => rand(50, 500)]),
                    'created_at' => now()->subMinutes(rand(1, 30)),
                ];
            }

            DB::table('job_logs')->insert($logs);
        }

        // Create some failed job entries
        $failedJobs = [
            [
                'uuid' => 'failed-job-1',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'failed-job-1', 'displayName' => 'ProcessUploadJob']),
                'exception' => 'Exception: File not found in storage',
                'failed_at' => now()->subHours(3),
                'user_id' => $user1->id,
                'file_name' => 'missing_file.csv',
                'retry_count' => 1,
            ],
            [
                'uuid' => 'failed-job-2',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => 'failed-job-2', 'displayName' => 'ProcessUploadJob']),
                'exception' => 'Exception: Invalid CSV format',
                'failed_at' => now()->subDays(1),
                'user_id' => $user2->id,
                'file_name' => 'invalid_format.csv',
                'retry_count' => 3,
            ],
        ];

        DB::table('failed_jobs')->insert($failedJobs);

        $this->command->info('Job status test data seeded successfully!');
    }
}
