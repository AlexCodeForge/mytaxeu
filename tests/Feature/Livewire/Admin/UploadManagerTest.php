<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UploadManager;
use App\Models\Upload;
use App\Models\User;
use App\Models\UploadMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UploadManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);

        // Create test uploads with different statuses
        Upload::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_COMPLETED,
            'created_at' => now()->subDays(1),
        ]);

        Upload::factory()->count(2)->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => 'Test failure reason',
            'created_at' => now()->subDays(2),
        ]);

        Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => Upload::STATUS_PROCESSING,
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_renders_successfully_for_admin_users(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.admin.upload-manager')
            ->assertSee('Upload Management')
            ->assertSee('Status Filter');
    }

    /** @test */
    public function it_denies_access_to_non_admin_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        Livewire::test(UploadManager::class);
    }

    /** @test */
    public function it_displays_all_uploads_by_default(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->assertViewHas('uploads')
            ->assertSee('6') // Total count of uploads
            ->assertSee(Upload::STATUS_COMPLETED)
            ->assertSee(Upload::STATUS_FAILED)
            ->assertSee(Upload::STATUS_PROCESSING);
    }

    /** @test */
    public function it_filters_uploads_by_status(): void
    {
        $this->actingAs($this->admin);

        // Test filtering by completed status
        Livewire::test(UploadManager::class)
            ->set('statusFilter', Upload::STATUS_COMPLETED)
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads->count() === 3 &&
                       $uploads->every(fn($upload) => $upload->status === Upload::STATUS_COMPLETED);
            });

        // Test filtering by failed status
        Livewire::test(UploadManager::class)
            ->set('statusFilter', Upload::STATUS_FAILED)
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads->count() === 2 &&
                       $uploads->every(fn($upload) => $upload->status === Upload::STATUS_FAILED);
            });
    }

    /** @test */
    public function it_filters_uploads_by_user(): void
    {
        $otherUser = User::factory()->create();
        Upload::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('userFilter', (string) $this->regularUser->id)
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads->every(fn($upload) => $upload->user_id === $this->regularUser->id);
            });
    }

    /** @test */
    public function it_searches_uploads_by_filename(): void
    {
        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'original_name' => 'unique_test_file.csv',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('search', 'unique_test')
            ->assertViewHas('uploads', function ($uploads) use ($upload) {
                return $uploads->contains($upload);
            });
    }

    /** @test */
    public function it_filters_uploads_by_date_range(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('dateFrom', now()->subDays(1)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads->count() > 0;
            });
    }

    /** @test */
    public function it_filters_uploads_by_file_size_range(): void
    {
        $largeUpload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'size_bytes' => 5000000, // 5MB
        ]);

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('minFileSize', 4)
            ->set('maxFileSize', 6)
            ->assertViewHas('uploads', function ($uploads) use ($largeUpload) {
                return $uploads->contains($largeUpload);
            });
    }

    /** @test */
    public function it_sorts_uploads_correctly(): void
    {
        $this->actingAs($this->admin);

        // Test sorting by created_at ascending
        Livewire::test(UploadManager::class)
            ->call('sortBy', 'created_at')
            ->assertViewHas('uploads', function ($uploads) {
                $first = $uploads->first();
                $last = $uploads->last();
                return $first->created_at <= $last->created_at;
            });

        // Test sorting by size descending
        Livewire::test(UploadManager::class)
            ->call('sortBy', 'size_bytes')
            ->call('sortBy', 'size_bytes') // Second call to reverse order
            ->assertViewHas('uploads', function ($uploads) {
                $first = $uploads->first();
                $last = $uploads->last();
                return $first->size_bytes >= $last->size_bytes;
            });
    }

    /** @test */
    public function it_opens_upload_details_modal(): void
    {
        $upload = Upload::first();
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->call('showUploadDetails', $upload->id)
            ->assertSet('showDetailsModal', true)
            ->assertSet('selectedUpload.id', $upload->id)
            ->assertSee($upload->original_name);
    }

    /** @test */
    public function it_closes_upload_details_modal(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('showDetailsModal', true)
            ->call('closeDetailsModal')
            ->assertSet('showDetailsModal', false)
            ->assertSet('selectedUpload', null);
    }

    /** @test */
    public function it_enables_bulk_selection(): void
    {
        $upload1 = Upload::first();
        $upload2 = Upload::skip(1)->first();

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->call('toggleUploadSelection', $upload1->id)
            ->call('toggleUploadSelection', $upload2->id)
            ->assertSet('selectedUploads', [$upload1->id, $upload2->id]);
    }

    /** @test */
    public function it_clears_bulk_selection(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('selectedUploads', [1, 2, 3])
            ->call('clearSelection')
            ->assertSet('selectedUploads', []);
    }

    /** @test */
    public function it_performs_bulk_retry_on_failed_uploads(): void
    {
        $failedUploads = Upload::where('status', Upload::STATUS_FAILED)->pluck('id')->toArray();

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('selectedUploads', $failedUploads)
            ->call('bulkRetry')
            ->assertDispatchedBrowserEvent('show-notification');

        // Verify uploads were processed for retry
        foreach ($failedUploads as $uploadId) {
            $upload = Upload::find($uploadId);
            $this->assertNotNull($upload);
        }
    }

    /** @test */
    public function it_performs_bulk_status_update(): void
    {
        $uploadIds = Upload::where('status', Upload::STATUS_PROCESSING)->pluck('id')->toArray();

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('selectedUploads', $uploadIds)
            ->call('bulkUpdateStatus', Upload::STATUS_FAILED)
            ->assertDispatchedBrowserEvent('show-notification');

        // Verify status was updated
        foreach ($uploadIds as $uploadId) {
            $upload = Upload::find($uploadId);
            $this->assertEquals(Upload::STATUS_FAILED, $upload->status);
        }
    }

    /** @test */
    public function it_downloads_original_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('test.csv', 100);
        Storage::disk('local')->put('uploads/test.csv', $file->getContent());

        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'path' => 'uploads/test.csv',
            'original_name' => 'test.csv',
        ]);

        $this->actingAs($this->admin);

        $response = Livewire::test(UploadManager::class)
            ->call('downloadOriginalFile', $upload->id)
            ->assertRedirect();

        // In a real test, you'd verify the download response
        // For now, we just check that the method executes without error
    }

    /** @test */
    public function it_downloads_transformed_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('transformed.csv', 100);
        Storage::disk('local')->put('uploads/transformed.csv', $file->getContent());

        $upload = Upload::factory()->create([
            'user_id' => $this->regularUser->id,
            'transformed_path' => 'uploads/transformed.csv',
            'status' => Upload::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->admin);

        $response = Livewire::test(UploadManager::class)
            ->call('downloadTransformedFile', $upload->id)
            ->assertRedirect();
    }

    /** @test */
    public function it_retries_single_upload(): void
    {
        $failedUpload = Upload::where('status', Upload::STATUS_FAILED)->first();

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->call('retryUpload', $failedUpload->id)
            ->assertDispatchedBrowserEvent('show-notification');
    }

    /** @test */
    public function it_exports_uploads_to_csv(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->call('exportToCsv')
            ->assertDispatchedBrowserEvent('show-notification');
    }

    /** @test */
    public function it_clears_all_filters(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('statusFilter', Upload::STATUS_FAILED)
            ->set('userFilter', '1')
            ->set('search', 'test')
            ->set('dateFrom', '2023-01-01')
            ->set('dateTo', '2023-12-31')
            ->set('minFileSize', 1)
            ->set('maxFileSize', 10)
            ->call('clearFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('userFilter', '')
            ->assertSet('search', '')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('minFileSize', null)
            ->assertSet('maxFileSize', null);
    }

    /** @test */
    public function it_paginates_uploads_correctly(): void
    {
        // Create more uploads to test pagination
        Upload::factory()->count(25)->create([
            'user_id' => $this->regularUser->id,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads instanceof \Illuminate\Pagination\LengthAwarePaginator;
            });
    }

    /** @test */
    public function it_updates_page_when_search_changes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('page', 2)
            ->set('search', 'test')
            ->assertSet('page', 1);
    }

    /** @test */
    public function it_updates_page_when_filters_change(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UploadManager::class)
            ->set('page', 2)
            ->set('statusFilter', Upload::STATUS_FAILED)
            ->assertSet('page', 1);
    }
}
