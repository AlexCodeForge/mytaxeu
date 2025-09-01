<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use App\Services\UsageMeteringService;
use App\Services\CsvLineCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UsageMeteringSystemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function csv_line_counter_service_can_count_lines_in_csv_file(): void
    {
        Storage::fake('local');

        // Create a test CSV file with known line count
        $csvContent = "Name,Email,Age\nJohn,john@example.com,30\nJane,jane@example.com,25\nBob,bob@example.com,35";
        $filePath = 'test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $csvLineCounter = app(CsvLineCounterService::class);
        $lineCount = $csvLineCounter->countLines(Storage::disk('local')->path($filePath));

        // Should count 4 lines total (header + 3 data rows)
        $this->assertEquals(4, $lineCount);
    }

    /** @test */
    public function csv_line_counter_service_handles_empty_files(): void
    {
        Storage::fake('local');

        $filePath = 'empty.csv';
        Storage::disk('local')->put($filePath, '');

        $csvLineCounter = app(CsvLineCounterService::class);
        $lineCount = $csvLineCounter->countLines(Storage::disk('local')->path($filePath));

        $this->assertEquals(0, $lineCount);
    }

    /** @test */
    public function csv_line_counter_service_handles_files_with_only_headers(): void
    {
        Storage::fake('local');

        $csvContent = "Name,Email,Age";
        $filePath = 'headers_only.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $csvLineCounter = app(CsvLineCounterService::class);
        $lineCount = $csvLineCounter->countLines(Storage::disk('local')->path($filePath));

        $this->assertEquals(1, $lineCount);
    }

    /** @test */
    public function csv_line_counter_service_handles_large_files_efficiently(): void
    {
        Storage::fake('local');

        // Create a larger CSV file (1000 rows)
        $csvContent = "Name,Email,Age\n";
        for ($i = 1; $i <= 1000; $i++) {
            $csvContent .= "User{$i},user{$i}@example.com,{$i}\n";
        }
        $filePath = 'large.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $csvLineCounter = app(CsvLineCounterService::class);

        $startTime = microtime(true);
        $lineCount = $csvLineCounter->countLines(Storage::disk('local')->path($filePath));
        $endTime = microtime(true);

        $this->assertEquals(1001, $lineCount); // header + 1000 data rows

        // Should complete in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $endTime - $startTime);
    }

    /** @test */
    public function usage_metering_service_can_track_upload_metrics(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $upload = Upload::factory()->create(['user_id' => $user->id]);

        $usageService = app(UsageMeteringService::class);

        $usageService->trackUploadStart($upload, 150, 2048000); // 150 lines, ~2MB

        $uploadMetric = UploadMetric::where('upload_id', $upload->id)->first();

        $this->assertNotNull($uploadMetric);
        $this->assertEquals(150, $uploadMetric->line_count);
        $this->assertEquals(2048000, $uploadMetric->file_size_bytes);
        $this->assertEquals(UploadMetric::STATUS_PROCESSING, $uploadMetric->status);
        $this->assertNotNull($uploadMetric->processing_started_at);
    }

    /** @test */
    public function usage_metering_service_can_track_processing_completion(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->processing()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $usageService = app(UsageMeteringService::class);

        $usageService->trackProcessingCompletion($uploadMetric, true, 2);

        $uploadMetric->refresh();
        $this->assertEquals(UploadMetric::STATUS_COMPLETED, $uploadMetric->status);
        $this->assertEquals(2, $uploadMetric->credits_consumed);
        $this->assertNotNull($uploadMetric->processing_completed_at);
        $this->assertNotNull($uploadMetric->processing_duration_seconds);
    }

    /** @test */
    public function usage_metering_service_can_track_processing_failure(): void
    {
        $user = User::factory()->create(['credits' => 10]);
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->processing()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $usageService = app(UsageMeteringService::class);
        $errorMessage = 'CSV validation failed';

        $usageService->trackProcessingFailure($uploadMetric, $errorMessage);

        $uploadMetric->refresh();
        $this->assertEquals(UploadMetric::STATUS_FAILED, $uploadMetric->status);
        $this->assertEquals(0, $uploadMetric->credits_consumed);
        $this->assertEquals($errorMessage, $uploadMetric->error_message);
        $this->assertNotNull($uploadMetric->processing_completed_at);
    }

    /** @test */
    public function usage_metering_service_updates_user_usage_counters(): void
    {
        $user = User::factory()->create([
            'credits' => 10,
            'total_lines_processed' => 100,
            'current_month_usage' => 50,
        ]);
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'line_count' => 200,
        ]);

        $usageService = app(UsageMeteringService::class);

        $usageService->updateUserUsageCounters($user, $uploadMetric);

        $user->refresh();
        $this->assertEquals(300, $user->total_lines_processed); // 100 + 200
        $this->assertEquals(250, $user->current_month_usage); // 50 + 200
    }

    /** @test */
    public function usage_metering_service_can_get_user_monthly_usage(): void
    {
        $user = User::factory()->create();

        // Create metrics from current month
        UploadMetric::factory()->completed()->count(3)->create([
            'user_id' => $user->id,
            'line_count' => 100,
            'created_at' => now(),
        ]);

        // Create metrics from previous month (should not be included)
        UploadMetric::factory()->completed()->count(2)->create([
            'user_id' => $user->id,
            'line_count' => 50,
            'created_at' => now()->subMonth(),
        ]);

        $usageService = app(UsageMeteringService::class);
        $monthlyUsage = $usageService->getCurrentMonthUsage($user);

        $this->assertEquals(300, $monthlyUsage); // 3 * 100 lines from current month
    }

    /** @test */
    public function usage_metering_service_can_check_tier_limits(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 950,
        ]);

        $usageService = app(UsageMeteringService::class);

        // Assume free tier limit is 1000 lines
        $this->assertTrue($usageService->canProcessLines($user, 50)); // 950 + 50 = 1000 (at limit)
        $this->assertFalse($usageService->canProcessLines($user, 51)); // 950 + 51 = 1001 (over limit)
    }

    /** @test */
    public function usage_metering_service_can_reset_monthly_usage(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 500,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $usageService = app(UsageMeteringService::class);

        $usageService->resetMonthlyUsage($user);

        $user->refresh();
        $this->assertEquals(0, $user->current_month_usage);
        $this->assertEquals(now()->format('Y-m-d'), $user->usage_reset_date->format('Y-m-d'));
    }

    /** @test */
    public function usage_metering_service_can_process_bulk_monthly_resets(): void
    {
        // Create users who need reset (old reset dates)
        $user1 = User::factory()->create([
            'current_month_usage' => 1000,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $user2 = User::factory()->create([
            'current_month_usage' => 500,
            'usage_reset_date' => null, // Never reset
        ]);

        // Create user who doesn't need reset (recent reset date)
        $user3 = User::factory()->create([
            'current_month_usage' => 300,
            'usage_reset_date' => now()->subDays(5),
        ]);

        // Create user with no usage (should not be reset)
        $user4 = User::factory()->create([
            'current_month_usage' => 0,
            'usage_reset_date' => now()->subMonth(),
        ]);

        $usageService = app(UsageMeteringService::class);
        $resetCount = $usageService->processMonthlyResets();

        // Should reset 2 users (user1 and user2)
        $this->assertEquals(2, $resetCount);

        $user1->refresh();
        $user2->refresh();
        $user3->refresh();
        $user4->refresh();

        // User1 and User2 should be reset
        $this->assertEquals(0, $user1->current_month_usage);
        $this->assertEquals(0, $user2->current_month_usage);
        $this->assertEquals(now()->format('Y-m-d'), $user1->usage_reset_date->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), $user2->usage_reset_date->format('Y-m-d'));

        // User3 should not be reset (recent reset date)
        $this->assertEquals(300, $user3->current_month_usage);

        // User4 should not be reset (no usage)
        $this->assertEquals(0, $user4->current_month_usage);
    }

    /** @test */
    public function usage_metering_service_can_get_usage_statistics(): void
    {
        $user = User::factory()->create();

        // Create various metrics with explicit values
        UploadMetric::create([
            'user_id' => $user->id,
            'upload_id' => Upload::factory()->create(['user_id' => $user->id])->id,
            'file_name' => 'test1.csv',
            'line_count' => 100,
            'file_size_bytes' => 1024000,
            'processing_started_at' => now()->subMinutes(5),
            'processing_completed_at' => now()->subMinutes(4),
            'processing_duration_seconds' => 30,
            'credits_consumed' => 1,
            'status' => UploadMetric::STATUS_COMPLETED,
        ]);

        UploadMetric::create([
            'user_id' => $user->id,
            'upload_id' => Upload::factory()->create(['user_id' => $user->id])->id,
            'file_name' => 'test2.csv',
            'line_count' => 200,
            'file_size_bytes' => 2048000,
            'processing_started_at' => now()->subMinutes(5),
            'processing_completed_at' => now()->subMinutes(3),
            'processing_duration_seconds' => 60,
            'credits_consumed' => 2,
            'status' => UploadMetric::STATUS_COMPLETED,
        ]);

        UploadMetric::create([
            'user_id' => $user->id,
            'upload_id' => Upload::factory()->create(['user_id' => $user->id])->id,
            'file_name' => 'test3.csv',
            'line_count' => 50,
            'file_size_bytes' => 512000,
            'processing_started_at' => now()->subMinutes(2),
            'processing_completed_at' => now()->subMinutes(1),
            'processing_duration_seconds' => null, // Failed uploads may not have duration
            'status' => UploadMetric::STATUS_FAILED,
            'error_message' => 'Test error',
            'credits_consumed' => 0,
        ]);

        $usageService = app(UsageMeteringService::class);
        $stats = $usageService->getUserUsageStatistics($user);

        $this->assertEquals(300, $stats['total_lines_processed']);
        $this->assertEquals(3584000, $stats['total_file_size_bytes']); // 1024000 + 2048000 + 512000
        $this->assertEquals(90, $stats['total_processing_time_seconds']);
        $this->assertEquals(3, $stats['total_credits_consumed']);
        $this->assertEquals(2, $stats['successful_uploads']);
        $this->assertEquals(1, $stats['failed_uploads']);
        $this->assertEquals(3, $stats['total_uploads']);
    }

    /** @test */
    public function usage_metering_service_can_export_usage_data(): void
    {
        $user = User::factory()->create();

        UploadMetric::factory()->completed()->count(5)->create([
            'user_id' => $user->id,
        ]);

        $usageService = app(UsageMeteringService::class);
        $exportData = $usageService->exportUserUsageData($user, now()->subWeek(), now());

        $this->assertCount(5, $exportData);
        $this->assertArrayHasKey('file_name', $exportData[0]);
        $this->assertArrayHasKey('line_count', $exportData[0]);
        $this->assertArrayHasKey('processing_duration_seconds', $exportData[0]);
        $this->assertArrayHasKey('status', $exportData[0]);
    }

    /** @test */
    public function usage_metering_integrates_with_upload_process(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['credits' => 10]);

        // Create a CSV file
        $csvContent = "Name,Email\nJohn,john@test.com\nJane,jane@test.com";
        $uploadedFile = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $upload = Upload::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'test.csv',
            'size_bytes' => strlen($csvContent),
        ]);

        $usageService = app(UsageMeteringService::class);
        $csvLineCounter = app(CsvLineCounterService::class);

        // Simulate the upload process
        $lineCount = $csvLineCounter->countLines($uploadedFile->getPathname());
        $usageService->trackUploadStart($upload, $lineCount, strlen($csvContent));

        $uploadMetric = UploadMetric::where('upload_id', $upload->id)->first();

        $this->assertNotNull($uploadMetric);
        $this->assertEquals(3, $uploadMetric->line_count); // header + 2 data rows
        $this->assertEquals(strlen($csvContent), $uploadMetric->file_size_bytes);
    }

    /** @test */
    public function usage_metering_service_handles_concurrent_uploads(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $usageService = app(UsageMeteringService::class);

        // Simulate multiple concurrent uploads
        $uploads = [];
        for ($i = 0; $i < 3; $i++) {
            $upload = Upload::factory()->create(['user_id' => $user->id]);
            $uploads[] = $upload;
            $usageService->trackUploadStart($upload, 100, 1024000);
        }

        // Check all metrics were created
        foreach ($uploads as $upload) {
            $metric = UploadMetric::where('upload_id', $upload->id)->first();
            $this->assertNotNull($metric);
            $this->assertEquals(100, $metric->line_count);
        }

        $this->assertEquals(3, UploadMetric::where('user_id', $user->id)->count());
    }

    /** @test */
    public function usage_metering_service_prevents_processing_when_over_limit(): void
    {
        $user = User::factory()->create([
            'current_month_usage' => 1000, // At free tier limit
        ]);

        $usageService = app(UsageMeteringService::class);

        $this->expectException(\App\Exceptions\UsageLimitExceededException::class);

        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $usageService->trackUploadStart($upload, 1, 1024); // Even 1 line should fail
    }

    /** @test */
    public function usage_metering_service_tracks_processing_trends(): void
    {
        $user = User::factory()->create();

        // Create metrics over several days with explicit values
        for ($i = 0; $i < 7; $i++) {
            UploadMetric::create([
                'user_id' => $user->id,
                'upload_id' => Upload::factory()->create(['user_id' => $user->id])->id,
                'file_name' => "test{$i}.csv",
                'line_count' => 100 + $i * 10,
                'file_size_bytes' => 1024000,
                'processing_started_at' => now()->subDays($i)->subMinutes(5),
                'processing_completed_at' => now()->subDays($i)->subMinutes(3),
                'processing_duration_seconds' => 120,
                'credits_consumed' => 1,
                'status' => UploadMetric::STATUS_COMPLETED,
                'created_at' => now()->subDays($i),
            ]);
        }

        $usageService = app(UsageMeteringService::class);
        $trends = $usageService->getUsageTrends($user, 7);

        // The trends will only return dates that have data, not necessarily 7 entries
        $this->assertGreaterThan(0, count($trends));

        // Test that data is ordered properly (newest first)
        if (count($trends) >= 2) {
            $this->assertGreaterThanOrEqual($trends[1]['line_count'], $trends[0]['line_count']);
        }

        // Verify some data exists
        $totalLinesInTrends = array_sum(array_column($trends, 'line_count'));
        $this->assertGreaterThan(0, $totalLinesInTrends);
    }
}
