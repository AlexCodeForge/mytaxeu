<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function authenticated_user_can_download_their_own_processed_upload(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename="' . pathinfo($upload->original_name, PATHINFO_FILENAME) . '_procesado_' . $upload->processed_at->format('Y-m-d') . '.csv"');
    }

    /** @test */
    public function user_cannot_download_uploads_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $upload = Upload::factory()->completed()->create([
            'user_id' => $owner->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $this->actingAs($otherUser)
            ->get(route('download.upload', $upload))
            ->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_download_uploads(): void
    {
        $upload = Upload::factory()->completed()->create([
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $this->get(route('download.upload', $upload))
            ->assertRedirect('/login'); // Assuming auth middleware redirects to login
    }

    /** @test */
    public function user_cannot_download_upload_without_transformed_file(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->create([
            'user_id' => $user->id,
            'transformed_path' => null,
            'status' => Upload::STATUS_PROCESSING,
        ]);

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(404);
    }

    /** @test */
    public function user_cannot_download_upload_if_file_does_not_exist_in_storage(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/nonexistent.csv',
        ]);

        // Don't create the file in storage

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(404);
    }

    /** @test */
    public function download_generates_correct_filename_with_processed_date(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'original_name' => 'my-file.csv',
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
            'processed_at' => now()->parse('2025-01-15 10:30:00'),
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $response = $this->actingAs($user)
            ->get(route('download.upload', $upload));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename="my-file_procesado_2025-01-15.csv"');
    }

    /** @test */
    public function download_generates_filename_with_current_date_when_processed_at_is_null(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'original_name' => 'my-file.csv',
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
            'processed_at' => null,
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $response = $this->actingAs($user)
            ->get(route('download.upload', $upload));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename="my-file_procesado_' . date('Y-m-d') . '.csv"');
    }

    /** @test */
    public function download_handles_different_file_extensions(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'original_name' => 'test-file.txt',
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        // Create the file in storage
        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $response = $this->actingAs($user)
            ->get(route('download.upload', $upload));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename="test-file_procesado_' . $upload->processed_at->format('Y-m-d') . '.csv"');
    }

    /** @test */
    public function download_works_with_different_disk_configurations(): void
    {
        // Test with a different disk (public in this case)
        Storage::fake('public');

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'disk' => 'public',
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        // Create the file in the public disk
        Storage::disk('public')->put($upload->transformed_path, 'test,file,content');

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(200);
    }

    /** @test */
    public function download_content_matches_file_content(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        $fileContent = "Name,Email,Country\nJohn,john@test.com,ES\nJane,jane@test.com,ES";
        Storage::disk('local')->put($upload->transformed_path, $fileContent);

        $response = $this->actingAs($user)
            ->get(route('download.upload', $upload));

        $response->assertStatus(200);
        $this->assertEquals($fileContent, $response->getContent());
    }

    /** @test */
    public function download_returns_correct_content_type_for_csv(): void
    {
        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $response = $this->actingAs($user)
            ->get(route('download.upload', $upload));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function download_logs_successful_access(): void
    {
        Log::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $this->actingAs($user)
            ->get(route('download.upload', $upload));

        Log::assertLogged('info', function ($message, $context) use ($user, $upload) {
            return $message === 'Download attempt' &&
                   $context['user_id'] === $user->id &&
                   $context['upload_id'] === $upload->id;
        });

        Log::assertLogged('info', function ($message, $context) use ($user, $upload) {
            return $message === 'File download successful' &&
                   $context['user_id'] === $user->id &&
                   $context['upload_id'] === $upload->id;
        });
    }

    /** @test */
    public function download_logs_unauthorized_access_attempts(): void
    {
        Log::fake();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $upload = Upload::factory()->completed()->create([
            'user_id' => $owner->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        $this->actingAs($otherUser)
            ->get(route('download.upload', $upload))
            ->assertStatus(403);

        Log::assertLogged('warning', function ($message, $context) use ($otherUser, $upload, $owner) {
            return $message === 'Unauthorized download attempt' &&
                   $context['user_id'] === $otherUser->id &&
                   $context['upload_id'] === $upload->id &&
                   $context['owner_id'] === $owner->id;
        });
    }

    /** @test */
    public function download_logs_attempts_for_missing_files(): void
    {
        Log::fake();

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/nonexistent.csv',
        ]);

        // Don't create the file in storage

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(404);

        Log::assertLogged('error', function ($message, $context) use ($user, $upload) {
            return $message === 'Download attempt for missing file' &&
                   $context['user_id'] === $user->id &&
                   $context['upload_id'] === $upload->id;
        });
    }

    /** @test */
    public function download_enforces_rate_limiting(): void
    {
        RateLimiter::clear('downloads');

        $user = User::factory()->create();
        $upload = Upload::factory()->completed()->create([
            'user_id' => $user->id,
            'transformed_path' => 'uploads/1/output/test_procesado.csv',
        ]);

        Storage::disk('local')->put($upload->transformed_path, 'test,file,content');

        // Simulate hitting rate limit by making many requests
        // The rate limiter allows 60 requests per minute
        for ($i = 0; $i < 61; $i++) {
            RateLimiter::hit('downloads:' . $user->id);
        }

        $this->actingAs($user)
            ->get(route('download.upload', $upload))
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many download attempts. Please try again later.']);
    }

    /** @test */
    public function download_rate_limiting_is_per_user(): void
    {
        RateLimiter::clear('downloads');

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $upload1 = Upload::factory()->completed()->create([
            'user_id' => $user1->id,
            'transformed_path' => 'uploads/1/output/test1.csv',
        ]);

        $upload2 = Upload::factory()->completed()->create([
            'user_id' => $user2->id,
            'transformed_path' => 'uploads/2/output/test2.csv',
        ]);

        Storage::disk('local')->put($upload1->transformed_path, 'test,file,content1');
        Storage::disk('local')->put($upload2->transformed_path, 'test,file,content2');

        // Hit rate limit for user1
        for ($i = 0; $i < 61; $i++) {
            RateLimiter::hit('downloads:' . $user1->id);
        }

        // User1 should be rate limited
        $this->actingAs($user1)
            ->get(route('download.upload', $upload1))
            ->assertStatus(429);

        // User2 should still be able to download
        $this->actingAs($user2)
            ->get(route('download.upload', $upload2))
            ->assertStatus(200);
    }
}
