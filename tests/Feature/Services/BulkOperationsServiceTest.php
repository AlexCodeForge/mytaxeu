<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\Upload;
use App\Services\BulkOperationsService;
use App\Jobs\ProcessUploadJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulkOperationsService $bulkService;
    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bulkService = new BulkOperationsService();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    /** @test */
    public function it_can_retry_multiple_failed_uploads(): void
    {
        Queue::fake();

        $uploads = Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => 'Test error',
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);
        $this->assertEquals(0, $result['failed_count']);

        // Verify uploads are marked as queued
        foreach ($uploads as $upload) {
            $fresh = $upload->fresh();
            $this->assertEquals(Upload::STATUS_QUEUED, $fresh->status);
            $this->assertNull($fresh->failure_reason);
        }

        // Verify jobs were dispatched
        Queue::assertPushed(ProcessUploadJob::class, 3);
    }

    /** @test */
    public function it_skips_already_completed_uploads_in_retry(): void
    {
        Queue::fake();

        $failedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $completedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $uploadIds = [$failedUpload->id, $completedUpload->id];

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed_count']);
        $this->assertEquals(1, $result['skipped_count']);

        // Only failed upload should be retried
        $this->assertEquals(Upload::STATUS_QUEUED, $failedUpload->fresh()->status);
        $this->assertEquals(Upload::STATUS_COMPLETED, $completedUpload->fresh()->status);

        Queue::assertPushed(ProcessUploadJob::class, 1);
    }

    /** @test */
    public function it_can_bulk_update_upload_status(): void
    {
        $uploads = Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_QUEUED,
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $result = $this->bulkService->updateUploadStatus($uploadIds, Upload::STATUS_CANCELLED, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);
        $this->assertEquals(0, $result['failed_count']);

        // Verify status updates
        foreach ($uploads as $upload) {
            $this->assertEquals(Upload::STATUS_CANCELLED, $upload->fresh()->status);
        }
    }

    /** @test */
    public function it_prevents_invalid_status_transitions(): void
    {
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $result = $this->bulkService->updateUploadStatus([$upload->id], Upload::STATUS_PROCESSING, $this->admin->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status transition', $result['message']);

        // Status should remain unchanged
        $this->assertEquals(Upload::STATUS_COMPLETED, $upload->fresh()->status);
    }

    /** @test */
    public function it_can_bulk_delete_uploads(): void
    {
        $uploads = Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $result = $this->bulkService->deleteUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);

        // Verify uploads are deleted
        foreach ($uploadIds as $uploadId) {
            $this->assertDatabaseMissing('uploads', ['id' => $uploadId]);
        }
    }

    /** @test */
    public function it_prevents_deletion_of_processing_uploads(): void
    {
        $processingUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_PROCESSING,
        ]);

        $failedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = [$processingUpload->id, $failedUpload->id];

        $result = $this->bulkService->deleteUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed_count']);
        $this->assertEquals(1, $result['skipped_count']);

        // Processing upload should still exist
        $this->assertDatabaseHas('uploads', ['id' => $processingUpload->id]);
        $this->assertDatabaseMissing('uploads', ['id' => $failedUpload->id]);
    }

    /** @test */
    public function it_can_bulk_suspend_users(): void
    {
        $users = User::factory()->count(3)->create(['is_suspended' => false]);

        $userIds = $users->pluck('id')->toArray();

        $result = $this->bulkService->suspendUsers($userIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);

        // Verify users are suspended
        foreach ($users as $user) {
            $fresh = $user->fresh();
            $this->assertTrue($fresh->is_suspended);
            $this->assertNotNull($fresh->suspended_at);
            $this->assertEquals($this->admin->id, $fresh->suspended_by);
        }
    }

    /** @test */
    public function it_prevents_suspension_of_admin_users(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true, 'is_suspended' => false]);
        $regularUser = User::factory()->create(['is_admin' => false, 'is_suspended' => false]);

        $userIds = [$adminUser->id, $regularUser->id];

        $result = $this->bulkService->suspendUsers($userIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed_count']);
        $this->assertEquals(1, $result['skipped_count']);

        // Admin should not be suspended
        $this->assertFalse($adminUser->fresh()->is_suspended);
        $this->assertTrue($regularUser->fresh()->is_suspended);
    }

    /** @test */
    public function it_can_bulk_activate_users(): void
    {
        $users = User::factory()->count(3)->create([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspended_by' => $this->admin->id,
        ]);

        $userIds = $users->pluck('id')->toArray();

        $result = $this->bulkService->activateUsers($userIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed_count']);

        // Verify users are activated
        foreach ($users as $user) {
            $fresh = $user->fresh();
            $this->assertFalse($fresh->is_suspended);
            $this->assertNull($fresh->suspended_at);
            $this->assertNull($fresh->suspended_by);
        }
    }

    /** @test */
    public function it_logs_bulk_operations_for_audit(): void
    {
        $uploads = Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        // Verify audit log entries
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $this->admin->id,
            'action' => 'bulk_retry_uploads',
        ]);
    }

    /** @test */
    public function it_handles_large_bulk_operations_with_batching(): void
    {
        Queue::fake();

        // Create 150 failed uploads
        $uploads = Upload::factory()->count(150)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(150, $result['processed_count']);

        // Verify all uploads are updated
        $queuedCount = Upload::whereIn('id', $uploadIds)
            ->where('status', Upload::STATUS_QUEUED)
            ->count();

        $this->assertEquals(150, $queuedCount);
    }

    /** @test */
    public function it_provides_detailed_operation_results(): void
    {
        $failedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $completedUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $uploadIds = [$failedUpload->id, $completedUpload->id];

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('processed_count', $result);
        $this->assertArrayHasKey('skipped_count', $result);
        $this->assertArrayHasKey('failed_count', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('details', $result);
    }

    /** @test */
    public function it_handles_non_existent_upload_ids_gracefully(): void
    {
        $validUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = [$validUpload->id, 999999, 999998]; // Include non-existent IDs

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed_count']);
        $this->assertEquals(2, $result['failed_count']);
    }

    /** @test */
    public function it_validates_operation_permissions(): void
    {
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        // Try to perform operation as non-admin user
        $result = $this->bulkService->retryUploads([$upload->id], $this->regularUser->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insufficient permissions', $result['message']);
    }

    /** @test */
    public function it_can_generate_bulk_operation_report(): void
    {
        $uploads = Upload::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
        ]);

        $uploadIds = $uploads->pluck('id')->toArray();

        $result = $this->bulkService->retryUploads($uploadIds, $this->admin->id);

        $report = $this->bulkService->generateOperationReport($result);

        $this->assertIsString($report);
        $this->assertStringContainsString('Bulk Operation Report', $report);
        $this->assertStringContainsString('Processed: 5', $report);
        $this->assertStringContainsString('Administrator: ' . $this->admin->name, $report);
    }
}

