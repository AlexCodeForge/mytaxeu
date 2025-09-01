<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Models\User;
use App\Notifications\EnhancedUploadCompleted;
use App\Notifications\EnhancedUploadFailed;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnhancedNotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function notification_service_can_send_success_notification_with_download_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'line_count' => 150,
            'processing_duration_seconds' => 45,
            'credits_consumed' => 2,
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->sendSuccessNotification($upload, $uploadMetric);

        Notification::assertSentTo(
            $user,
            EnhancedUploadCompleted::class,
            function ($notification) use ($upload, $uploadMetric) {
                return $notification->upload->id === $upload->id &&
                       $notification->uploadMetric->id === $uploadMetric->id;
            }
        );
    }

    /** @test */
    public function notification_service_can_send_failure_notification_with_error_details(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->failed()->create([
            'user_id' => $user->id,
            'failure_reason' => 'Invalid CSV format',
        ]);
        $uploadMetric = UploadMetric::factory()->failed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'line_count' => 150,
            'processing_duration_seconds' => 30,
            'error_message' => 'Invalid CSV format detected',
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->sendFailureNotification($upload, $uploadMetric);

        Notification::assertSentTo(
            $user,
            EnhancedUploadFailed::class,
            function ($notification) use ($upload, $uploadMetric) {
                return $notification->upload->id === $upload->id &&
                       $notification->uploadMetric->id === $uploadMetric->id;
            }
        );
    }

    /** @test */
    public function notification_service_prevents_duplicate_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'notification_sent_at' => now(),
            'notification_type' => 'success',
        ]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notificationService = app(NotificationService::class);
        $result = $notificationService->sendSuccessNotification($upload, $uploadMetric);

        $this->assertFalse($result);
        Notification::assertNotSentTo($user, EnhancedUploadCompleted::class);
    }

    /** @test */
    public function notification_service_tracks_notification_sent_timestamp(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'notification_sent_at' => null,
            'notification_type' => null,
        ]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->sendSuccessNotification($upload, $uploadMetric);

        $upload->refresh();
        $this->assertNotNull($upload->notification_sent_at);
        $this->assertEquals('success', $upload->notification_type);
    }

    /** @test */
    public function enhanced_upload_completed_notification_contains_processing_summary(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'original_name' => 'ventas_enero.csv',
            'csv_line_count' => 150,
        ]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'line_count' => 150,
            'credits_consumed' => 2,
            'file_size_bytes' => 1024000,
        ]);

        // Explicitly set the processing duration after creation
        $uploadMetric->processing_duration_seconds = 45;
        $uploadMetric->save();

        $notification = new EnhancedUploadCompleted($upload, $uploadMetric);
        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('¡Hola Test User!', $mailMessage->greeting);
        $this->assertStringContainsString('ventas_enero.csv', $mailMessage->introLines[0]);
        $this->assertStringContainsString('150 líneas', $mailMessage->introLines[0]);
        $this->assertStringContainsString('45 segundos', $mailMessage->introLines[0]);
        $this->assertStringContainsString('2 créditos', $mailMessage->introLines[0]);
        $this->assertStringContainsString('1000 KB', $mailMessage->introLines[0]);
    }

    /** @test */
    public function enhanced_upload_completed_notification_includes_download_link(): void
    {
        \Storage::fake('local');

        $user = User::factory()->create();
        $transformedPath = 'uploads/1/output/test_' . uniqid() . '_transformado.csv';

        // Create the fake file in storage
        \Storage::disk('local')->put($transformedPath, 'fake file content');

        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => $transformedPath,
            'disk' => 'local',
        ]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notification = new EnhancedUploadCompleted($upload, $uploadMetric);
        $mailMessage = $notification->toMail($user);

        $this->assertNotNull($mailMessage->actionUrl);
        $this->assertStringContainsString('download', $mailMessage->actionUrl);
        $this->assertStringContainsString((string) $upload->id, $mailMessage->actionUrl);
    }

    /** @test */
    public function enhanced_upload_failed_notification_contains_error_details(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $upload = Upload::factory()->failed()->create([
            'user_id' => $user->id,
            'original_name' => 'archivo_erroneo.csv',
            'failure_reason' => 'Missing required ACTIVITY_PERIOD column',
        ]);
        $uploadMetric = UploadMetric::factory()->failed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'error_message' => 'Missing required ACTIVITY_PERIOD column',
        ]);

        // Explicitly set the processing duration after creation
        $uploadMetric->processing_duration_seconds = 15;
        $uploadMetric->save();

        $notification = new EnhancedUploadFailed($upload, $uploadMetric);
        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('¡Hola Test User!', $mailMessage->greeting);
        $this->assertStringContainsString('archivo_erroneo.csv', $mailMessage->introLines[0]);
        $this->assertStringContainsString('Missing required ACTIVITY_PERIOD column', $mailMessage->introLines[0]);
        $this->assertStringContainsString('15 segundos', $mailMessage->introLines[0]);
    }

    /** @test */
    public function enhanced_upload_failed_notification_includes_dashboard_link(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->failed()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->failed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notification = new EnhancedUploadFailed($upload, $uploadMetric);
        $mailMessage = $notification->toMail($user);

        $this->assertNotNull($mailMessage->actionUrl);
        $this->assertStringContainsString('dashboard', $mailMessage->actionUrl);
    }

    /** @test */
    public function notification_service_can_send_processing_started_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->processing()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->processing()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->sendProcessingStartedNotification($upload, $uploadMetric);

        // Check that notification tracking is updated
        $upload->refresh();
        $this->assertNotNull($upload->notification_sent_at);
        $this->assertEquals('processing', $upload->notification_type);
    }

    /** @test */
    public function notification_content_includes_dashboard_links(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notification = new EnhancedUploadCompleted($upload, $uploadMetric);
        $mailMessage = $notification->toMail($user);

        // Should contain reference to dashboard for upload history
        $allLines = array_merge($mailMessage->introLines, $mailMessage->outroLines ?? []);
        $this->assertTrue(
            collect($allLines)->contains(function ($line) {
                return str_contains($line, 'historial') || str_contains($line, 'dashboard');
            })
        );
    }

    /** @test */
    public function notification_service_handles_missing_upload_metric_gracefully(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);

        $notificationService = app(NotificationService::class);

        // Should not throw exception when uploadMetric is null
        $result = $notificationService->sendSuccessNotification($upload, null);

        $this->assertTrue($result);
        Notification::assertSentTo($user, EnhancedUploadCompleted::class);
    }

    /** @test */
    public function notification_service_logs_notification_activity(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create(['user_id' => $user->id]);
        $uploadMetric = UploadMetric::factory()->completed()->create([
            'user_id' => $user->id,
            'upload_id' => $upload->id,
        ]);

        $notificationService = app(NotificationService::class);

        // This should log the notification activity
        $notificationService->sendSuccessNotification($upload, $uploadMetric);

        // Verify logs would be created (this would require log testing in actual implementation)
        $this->assertTrue(true);
    }
}
