<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadMetricTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function upload_metric_can_be_created_with_required_attributes(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);

        $uploadMetric = UploadMetric::create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => 'test.csv',
            'file_size_bytes' => 1024,
            'line_count' => 100,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('upload_metrics', [
            'id' => $uploadMetric->id,
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => 'test.csv',
            'file_size_bytes' => 1024,
            'line_count' => 100,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function upload_metric_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $this->assertInstanceOf(User::class, $uploadMetric->user);
        $this->assertEquals($user->id, $uploadMetric->user->id);
    }

    /** @test */
    public function upload_metric_belongs_to_upload(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $this->assertInstanceOf(Upload::class, $uploadMetric->upload);
        $this->assertEquals($upload->id, $uploadMetric->upload->id);
    }

    /** @test */
    public function user_has_many_upload_metrics(): void
    {
        $user = User::factory()->create();
        $upload1 = Upload::factory()->create(['user_id' => $user->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user->id]);

        $metric1 = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload1->id,
        ]);
        $metric2 = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload2->id,
        ]);

        $this->assertCount(2, $user->uploadMetrics);
        $this->assertTrue($user->uploadMetrics->contains($metric1));
        $this->assertTrue($user->uploadMetrics->contains($metric2));
    }

    /** @test */
    public function upload_has_one_upload_metric(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $this->assertInstanceOf(UploadMetric::class, $upload->uploadMetric);
        $this->assertEquals($uploadMetric->id, $upload->uploadMetric->id);
    }

    /** @test */
    public function upload_metric_has_correct_status_constants(): void
    {
        $this->assertEquals('pending', UploadMetric::STATUS_PENDING);
        $this->assertEquals('processing', UploadMetric::STATUS_PROCESSING);
        $this->assertEquals('completed', UploadMetric::STATUS_COMPLETED);
        $this->assertEquals('failed', UploadMetric::STATUS_FAILED);

        $this->assertEquals([
            'pending',
            'processing',
            'completed',
            'failed',
        ], UploadMetric::STATUSES);
    }

    /** @test */
    public function upload_metric_casts_attributes_correctly(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_size_bytes' => '1024',
            'line_count' => '100',
            'credits_consumed' => '5',
            'processing_duration_seconds' => '120',
            'processing_started_at' => '2024-01-01 10:00:00',
            'processing_completed_at' => '2024-01-01 10:02:00',
        ]);

        $this->assertIsInt($uploadMetric->file_size_bytes);
        $this->assertIsInt($uploadMetric->line_count);
        $this->assertIsInt($uploadMetric->credits_consumed);
        $this->assertIsInt($uploadMetric->processing_duration_seconds);
        $this->assertInstanceOf(\Carbon\Carbon::class, $uploadMetric->processing_started_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $uploadMetric->processing_completed_at);
    }

    /** @test */
    public function upload_metric_has_fillable_attributes(): void
    {
        $fillable = [
            'user_id',
            'upload_id',
            'file_name',
            'file_size_bytes',
            'line_count',
            'processing_started_at',
            'processing_completed_at',
            'processing_duration_seconds',
            'credits_consumed',
            'status',
            'error_message',
        ];

        $uploadMetric = new UploadMetric();

        $this->assertEquals($fillable, $uploadMetric->getFillable());
    }

    /** @test */
    public function upload_metric_calculates_processing_duration_automatically(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);

        $startTime = now();
        $endTime = $startTime->copy()->addMinutes(2);

        $uploadMetric = UploadMetric::create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => 'test.csv',
            'file_size_bytes' => 1024,
            'line_count' => 100,
            'processing_started_at' => $startTime,
            'processing_completed_at' => $endTime,
            'status' => 'completed',
        ]);

        // Trigger the update event to calculate duration
        $uploadMetric->save();

        $this->assertEquals(120, $uploadMetric->processing_duration_seconds);
    }

    /** @test */
    public function upload_metric_status_helper_methods_work_correctly(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);

        $pendingMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $processingMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'status' => 'processing',
        ]);

        $completedMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'status' => 'completed',
        ]);

        $failedMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'status' => 'failed',
        ]);

        $this->assertTrue($pendingMetric->isPending());
        $this->assertFalse($pendingMetric->isProcessing());
        $this->assertFalse($pendingMetric->isCompleted());
        $this->assertFalse($pendingMetric->isFailed());

        $this->assertFalse($processingMetric->isPending());
        $this->assertTrue($processingMetric->isProcessing());
        $this->assertFalse($processingMetric->isCompleted());
        $this->assertFalse($processingMetric->isFailed());

        $this->assertFalse($completedMetric->isPending());
        $this->assertFalse($completedMetric->isProcessing());
        $this->assertTrue($completedMetric->isCompleted());
        $this->assertFalse($completedMetric->isFailed());

        $this->assertFalse($failedMetric->isPending());
        $this->assertFalse($failedMetric->isProcessing());
        $this->assertFalse($failedMetric->isCompleted());
        $this->assertTrue($failedMetric->isFailed());
    }

    /** @test */
    public function upload_metric_can_scope_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $upload1 = Upload::factory()->create(['user_id' => $user1->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user2->id]);

        $metric1 = UploadMetric::factory()->create([
            'user_id' => $user1->id,
            'upload_id' => $upload1->id,
        ]);
        $metric2 = UploadMetric::factory()->create([
            'user_id' => $user2->id,
            'upload_id' => $upload2->id,
        ]);

        $user1Metrics = UploadMetric::forUser($user1->id)->get();
        $this->assertCount(1, $user1Metrics);
        $this->assertEquals($metric1->id, $user1Metrics->first()->id);
    }

    /** @test */
    public function upload_metric_can_scope_by_status(): void
    {
        $user = User::factory()->create();
        $upload1 = Upload::factory()->create(['user_id' => $user->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user->id]);

        $completedMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload1->id,
            'status' => 'completed',
        ]);
        $failedMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload2->id,
            'status' => 'failed',
        ]);

        $completedMetrics = UploadMetric::withStatus('completed')->get();
        $this->assertCount(1, $completedMetrics);
        $this->assertEquals($completedMetric->id, $completedMetrics->first()->id);
    }

    /** @test */
    public function upload_metric_can_scope_by_date_range(): void
    {
        $user = User::factory()->create();
        $upload1 = Upload::factory()->create(['user_id' => $user->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user->id]);

        $oldMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload1->id,
            'created_at' => now()->subDays(10),
        ]);
        $recentMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload2->id,
            'created_at' => now()->subDays(1),
        ]);

        $recentMetrics = UploadMetric::createdBetween(
            now()->subDays(7),
            now()
        )->get();

        $this->assertCount(1, $recentMetrics);
        $this->assertEquals($recentMetric->id, $recentMetrics->first()->id);
    }

    /** @test */
    public function upload_metric_can_get_formatted_file_size(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create(['user_id' => $user->id]);

        $smallMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_size_bytes' => 512,
        ]);

        $largeMetric = UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_size_bytes' => 1048576, // 1MB
        ]);

        $this->assertEquals('512 B', $smallMetric->getFormattedSizeAttribute());
        $this->assertEquals('1 MB', $largeMetric->getFormattedSizeAttribute());
    }

    /** @test */
    public function upload_metric_can_get_current_month_usage_for_user(): void
    {
        $user = User::factory()->create();
        $upload1 = Upload::factory()->create(['user_id' => $user->id]);
        $upload2 = Upload::factory()->create(['user_id' => $user->id]);
        $upload3 = Upload::factory()->create(['user_id' => $user->id]);

        // Current month metrics
        UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload1->id,
            'line_count' => 100,
            'created_at' => now(),
        ]);
        UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload2->id,
            'line_count' => 150,
            'created_at' => now(),
        ]);

        // Previous month metric
        UploadMetric::factory()->create([
            'user_id' => $user->id,
            'upload_id' => $upload3->id,
            'line_count' => 200,
            'created_at' => now()->subMonth(),
        ]);

        $currentMonthUsage = UploadMetric::getCurrentMonthUsageForUser($user->id);
        $this->assertEquals(250, $currentMonthUsage);
    }
}
