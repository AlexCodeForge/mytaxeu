<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Services\AdminExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Carbon\Carbon;

class AdminExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminExportService $exportService;
    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportService = new AdminExportService();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function it_can_export_uploads_to_csv(): void
    {
        // Create test uploads
        $uploads = Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $csvContent = $this->exportService->exportUploads();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('ID,"User Name",Email,Filename,Size,Status,"Created At","Processed At","Failure Reason"', $csvContent);

        foreach ($uploads as $upload) {
            $this->assertStringContainsString($upload->original_name, $csvContent);
            $this->assertStringContainsString($upload->user->email, $csvContent);
        }
    }

    /** @test */
    public function it_can_export_uploads_with_filters(): void
    {
        // Create uploads with different statuses
        $completedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $failedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        // Export only completed uploads
        $csvContent = $this->exportService->exportUploads(['status' => Upload::STATUS_COMPLETED]);

        $this->assertStringContainsString($completedUpload->original_name, $csvContent);
        $this->assertStringNotContainsString($failedUpload->original_name, $csvContent);
    }

    /** @test */
    public function it_can_export_uploads_for_specific_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $upload1 = Upload::factory()->create(['user_id' => $user1->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user2->id]);

        $csvContent = $this->exportService->exportUploads(['user_id' => $user1->id]);

        $this->assertStringContainsString($upload1->original_name, $csvContent);
        $this->assertStringNotContainsString($upload2->original_name, $csvContent);
    }

    /** @test */
    public function it_can_export_uploads_within_date_range(): void
    {
        $recentUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'created_at' => now(),
        ]);

        $oldUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subWeek(),
        ]);

        $csvContent = $this->exportService->exportUploads([
            'date_from' => now()->subDays(3)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $this->assertStringContainsString($recentUpload->original_name, $csvContent);
        $this->assertStringNotContainsString($oldUpload->original_name, $csvContent);
    }

    /** @test */
    public function it_can_export_users_to_csv(): void
    {
        $users = User::factory()->count(3)->create();

        $csvContent = $this->exportService->exportUsers();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('ID,Name,Email,"Created At","Uploads Count","Total Size","Success Rate",Credits', $csvContent);

        foreach ($users as $user) {
            $this->assertStringContainsString($user->name, $csvContent);
            $this->assertStringContainsString($user->email, $csvContent);
        }
    }

    /** @test */
    public function it_includes_user_statistics_in_user_export(): void
    {
        $user = User::factory()->create();

        // Create uploads for the user
        Upload::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => Upload::STATUS_COMPLETED,
            'size_bytes' => 1024,
        ]);

        Upload::factory()->create([
            'user_id' => $user->id,
            'status' => Upload::STATUS_FAILED,
            'size_bytes' => 2048,
        ]);

        $csvContent = $this->exportService->exportUsers();

        $this->assertStringContainsString($user->email, $csvContent);
        $this->assertStringContainsString('3', $csvContent); // Upload count
        $this->assertStringContainsString('66.7', $csvContent); // Success rate (2/3)
    }

    /** @test */
    public function it_can_filter_users_by_upload_activity(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        Upload::factory()->create(['user_id' => $activeUser->id]);
        // No uploads for inactive user

        $csvContent = $this->exportService->exportUsers(['has_uploads' => true]);

        $this->assertStringContainsString($activeUser->email, $csvContent);
        $this->assertStringNotContainsString($inactiveUser->email, $csvContent);
    }

    /** @test */
    public function it_can_export_system_metrics_report(): void
    {
        // Create test data
        Upload::factory()->count(5)->create(['status' => Upload::STATUS_COMPLETED]);
        Upload::factory()->count(2)->create(['status' => Upload::STATUS_FAILED]);
        User::factory()->count(10)->create();

        $csvContent = $this->exportService->exportSystemMetrics();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('Metric,Value,"Date Generated"', $csvContent);
        $this->assertStringContainsString('Total Users', $csvContent);
        $this->assertStringContainsString('Total Uploads', $csvContent);
        $this->assertStringContainsString('Success Rate', $csvContent);
    }

    /** @test */
    public function it_can_export_upload_metrics_report(): void
    {
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        UploadMetric::factory()->create([
            'upload_id' => $upload->id,
            'user_id' => $upload->user_id,
            'processing_duration_seconds' => 120,
            'credits_consumed' => 10,
        ]);

        $csvContent = $this->exportService->exportUploadMetrics();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('"Upload ID",User,"File Name","File Size","Line Count","Processing Time (seconds)","Credits Consumed",Status,"Created At"', $csvContent);
        $this->assertStringContainsString((string) $upload->id, $csvContent);
        $this->assertStringContainsString('120', $csvContent);
        $this->assertStringContainsString('10', $csvContent);
    }

    /** @test */
    public function it_handles_empty_data_gracefully(): void
    {
        // Clear all data
        Upload::query()->delete();
        User::where('id', '!=', $this->admin->id)->delete();

        $uploadsCsv = $this->exportService->exportUploads();
        $usersCsv = $this->exportService->exportUsers();

        $this->assertStringContainsString('ID,User Name,Email', $uploadsCsv);
        $this->assertStringContainsString('ID,Name,Email', $usersCsv);

        // Should only contain headers
        $this->assertEquals(2, substr_count($uploadsCsv, "\n")); // Header + empty line
        $this->assertGreaterThanOrEqual(2, substr_count($usersCsv, "\n")); // Header + admin user
    }

    /** @test */
    public function it_escapes_csv_special_characters(): void
    {
        $user = User::factory()->create([
            'name' => 'Test, User "With Quotes"',
            'email' => 'test@example.com',
        ]);

        Upload::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'file,with,commas.csv',
        ]);

        $csvContent = $this->exportService->exportUploads();

        $this->assertStringContainsString('"Test, User ""With Quotes"""', $csvContent);
        $this->assertStringContainsString('"file,with,commas.csv"', $csvContent);
    }

    /** @test */
    public function it_can_save_export_to_storage(): void
    {
        Storage::fake('local');

        Upload::factory()->count(3)->create();

        $filename = $this->exportService->saveUploadsExport();

        $this->assertIsString($filename);
        $this->assertStringStartsWith('exports/uploads_', $filename);
        $this->assertStringEndsWith('.csv', $filename);

        Storage::disk('local')->assertExists($filename);

        $content = Storage::disk('local')->get($filename);
        $this->assertStringContainsString('ID,"User Name",Email', $content);
    }

    /** @test */
    public function it_can_save_users_export_to_storage(): void
    {
        Storage::fake('local');

        User::factory()->count(3)->create();

        $filename = $this->exportService->saveUsersExport();

        $this->assertIsString($filename);
        $this->assertStringStartsWith('exports/users_', $filename);
        $this->assertStringEndsWith('.csv', $filename);

        Storage::disk('local')->assertExists($filename);

        $content = Storage::disk('local')->get($filename);
        $this->assertStringContainsString('ID,Name,Email', $content);
    }

    /** @test */
    public function it_can_generate_filename_with_timestamp(): void
    {
        $filename = $this->exportService->generateExportFilename('uploads');

        $this->assertStringStartsWith('exports/uploads_', $filename);
        $this->assertStringEndsWith('.csv', $filename);
        $this->assertMatchesRegularExpression('/uploads_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/', $filename);
    }

    /** @test */
    public function it_validates_export_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->exportService->exportUploads(['invalid_filter' => 'value']);
    }

    /** @test */
    public function it_limits_export_size_for_performance(): void
    {
        // Create many uploads
        Upload::factory()->count(1500)->create();

        $csvContent = $this->exportService->exportUploads();

        // Should limit to a reasonable number (e.g., 1000 records)
        $lineCount = substr_count($csvContent, "\n");
        $this->assertLessThanOrEqual(1002, $lineCount); // 1000 records + header + final newline
    }

    /** @test */
    public function it_can_export_with_custom_limit(): void
    {
        Upload::factory()->count(50)->create();

        $csvContent = $this->exportService->exportUploads(['limit' => 10]);

        $lineCount = substr_count($csvContent, "\n");
        $this->assertLessThanOrEqual(12, $lineCount); // 10 records + header + final newline
    }

    /** @test */
    public function it_formats_dates_consistently(): void
    {
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'created_at' => Carbon::parse('2023-12-25 14:30:00'),
            'processed_at' => Carbon::parse('2023-12-25 14:35:00'),
        ]);

        $csvContent = $this->exportService->exportUploads();

        $this->assertStringContainsString('2023-12-25 14:30:00', $csvContent);
        $this->assertStringContainsString('2023-12-25 14:35:00', $csvContent);
    }

    /** @test */
    public function it_includes_error_summary_in_system_metrics(): void
    {
        // Create uploads with different statuses
        Upload::factory()->count(8)->create(['status' => Upload::STATUS_COMPLETED]);
        Upload::factory()->count(2)->create([
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => 'Test error',
        ]);

        $csvContent = $this->exportService->exportSystemMetrics();

        $this->assertStringContainsString('Error Rate', $csvContent);
        $this->assertStringContainsString('20', $csvContent); // 2/10 = 20%
    }
}
