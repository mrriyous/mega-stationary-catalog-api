<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\SyncChange;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoCoverService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_server_generates_cover_when_upload_does_not_include_one(): void
    {
        Storage::fake('s3');
        Storage::put('covers/generated.jpg', 'generated-cover');
        $this->mock(VideoCoverService::class)
            ->shouldReceive('generate')->once()->withArgs(fn (string $path) => str_starts_with($path, 'videos/'))
            ->andReturn('covers/generated.jpg');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();

        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'AUTO-COVER',
            'product_name' => 'Automatic Cover',
            'normal_price' => '10.000',
            'wholesale_price' => '8.000',
            'video' => UploadedFile::fake()->create('product.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.cover_extension', 'jpg');

        $this->assertDatabaseHas('videos', ['product_code' => 'AUTO-COVER', 'cover_path' => 'covers/generated.jpg']);
    }

    public function test_cover_backfill_preserves_video_version_and_publishes_sync_change(): void
    {
        Storage::fake('s3');
        Storage::put('covers/backfill.jpg', 'generated-cover');
        $video = Video::factory()->create(['cover_path' => null]);
        $updatedAt = $video->updated_at->toISOString();
        $this->mock(VideoCoverService::class)
            ->shouldReceive('generate')->once()->with($video->video_path)
            ->andReturn('covers/backfill.jpg');

        $this->artisan('video-covers:generate')->assertSuccessful();

        $this->assertSame($updatedAt, $video->fresh()->updated_at->toISOString());
        $this->assertSame('covers/backfill.jpg', $video->fresh()->cover_path);
        $this->assertDatabaseHas('sync_changes', [
            'entity_type' => 'video',
            'entity_id' => $video->id,
            'action' => 'upsert',
        ]);
        $this->assertSame('jpg', SyncChange::latest('id')->first()->payload['cover_extension']);
    }

    public function test_admin_can_upload_video_and_listing_reports_server_data(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Promo', 'sort_order' => 0]);

        $response = $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-001',
            'product_name' => 'Buku Tulis',
            'description' => 'Buku tulis berkualitas untuk sekolah.',
            'normal_price' => 'Rp 20.000',
            'wholesale_price' => 'Rp 17.000',
            'video' => UploadedFile::fake()->create('product.mp4', 250, 'video/mp4'),
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.product_code', 'PRD-001')
            ->assertJsonPath('data.description', 'Buku tulis berkualitas untuk sekolah.')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonStructure(['data' => ['video_url', 'cover_url', 'video_size_bytes']]);
        $this->assertDatabaseHas('videos', [
            'product_code' => 'PRD-001',
            'category_id' => $category->id,
        ]);
        $video = Video::where('product_code', 'PRD-001')->firstOrFail();
        Storage::disk('s3')->assertExists($video->video_path);
        Storage::disk('s3')->assertExists($video->cover_path);

        $this->getJson('/api/videos')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_name', 'Buku Tulis')
            ->assertJsonPath('data.0.description', 'Buku tulis berkualitas untuk sekolah.');
    }

    public function test_returns_403_when_regular_user_uploads_video(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $category = Category::create(['name' => 'Produk', 'sort_order' => 0]);

        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-002',
            'product_name' => 'Pensil',
            'normal_price' => 'Rp 5.000',
            'wholesale_price' => 'Rp 4.000',
            'video' => UploadedFile::fake()->create('product.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertForbidden()
            ->assertJsonPath('message', 'Akses admin diperlukan.');
    }

    public function test_listing_filters_by_category_and_searches_code_or_name(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $stationery = Category::factory()->create(['name' => 'Stationery']);
        $promo = Category::factory()->create(['name' => 'Promo']);
        Video::factory()->for($stationery)->create([
            'product_code' => 'BOOK-001',
            'product_name' => 'Buku Tulis',
        ]);
        Video::factory()->for($stationery)->create([
            'product_code' => 'PEN-001',
            'product_name' => 'Pulpen Biru',
        ]);
        Video::factory()->for($promo)->create([
            'product_code' => 'BOOK-PROMO',
            'product_name' => 'Paket Promo',
            'description' => 'Tinta permanent spesial',
        ]);

        $this->getJson("/api/videos?category_id={$stationery->id}&search=book&per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('data.0.product_code', 'BOOK-001');

        $this->getJson('/api/videos?search=Biru')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_code', 'PEN-001');

        $this->getJson('/api/videos?search=permanent')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_code', 'BOOK-PROMO');
    }

    public function test_regular_user_never_receives_the_forbidden_price_tier(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'normal_price_access' => false,
            'wholesale_price_access' => true,
        ]);
        Sanctum::actingAs($user);
        $video = Video::factory()->create([
            'normal_price' => 'SECRET-NORMAL',
            'wholesale_price' => 'VISIBLE-WHOLESALE',
        ]);

        $this->getJson('/api/videos')
            ->assertOk()
            ->assertJsonPath('data.0.normal_price', null)
            ->assertJsonPath('data.0.wholesale_price', 'VISIBLE-WHOLESALE')
            ->assertJsonMissing(['normal_price' => 'SECRET-NORMAL']);
        $this->getJson("/api/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('data.normal_price', null)
            ->assertJsonPath('data.wholesale_price', 'VISIBLE-WHOLESALE');
    }

    public function test_category_listing_reports_live_server_video_counts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create(['name' => 'Produk']);
        Video::factory()->count(2)->for($category)->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Produk')
            ->assertJsonPath('data.0.videos_count', 2);
    }

    public function test_admin_can_upload_videos_with_the_same_product_code(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();

        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-001',
            'product_name' => 'Buku Tulis',
            'normal_price' => 'Rp 20.000',
            'wholesale_price' => 'Rp 17.000',
            'video' => UploadedFile::fake()->create('product.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-001',
            'product_name' => 'Buku Gambar',
            'normal_price' => 'Rp 25.000',
            'wholesale_price' => 'Rp 20.000',
            'video' => UploadedFile::fake()->create('product-dua.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(2, Video::where('product_code', 'PRD-001')->count());
    }

    public function test_admin_soft_deletes_video_and_keeps_media_files(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();

        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-009',
            'product_name' => 'Penghapus',
            'normal_price' => 'Rp 3.000',
            'wholesale_price' => 'Rp 2.500',
            'video' => UploadedFile::fake()->create('product.mp4', 10, 'video/mp4'),
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $video = Video::where('product_code', 'PRD-009')->firstOrFail();

        $this->deleteJson("/api/videos/{$video->id}")->assertNoContent();

        $this->assertSoftDeleted($video);
        Storage::disk('s3')->assertExists($video->video_path);
        Storage::disk('s3')->assertExists($video->cover_path);
        $this->getJson('/api/videos')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson("/api/videos/{$video->id}")->assertNotFound();
    }

    public function test_missing_video_file_is_marked_and_published_to_sync(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create());
        $video = Video::factory()->create([
            'video_path' => 'videos/missing.mp4',
            'video_file_available' => true,
        ]);

        $this->getJson("/api/videos/{$video->id}/download")->assertNotFound();

        $this->assertFalse($video->fresh()->video_file_available);
        $change = SyncChange::query()
            ->where('entity_type', 'video')
            ->where('entity_id', $video->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertFalse($change->payload['video_file_available']);
    }

    public function test_media_audit_updates_existing_availability_and_publishes_sync(): void
    {
        Storage::fake('s3');
        $available = Video::factory()->create([
            'video_path' => 'videos/available.mp4',
            'cover_path' => null,
        ]);
        $missing = Video::factory()->create([
            'video_path' => 'videos/missing.mp4',
            'cover_path' => 'covers/missing.jpg',
            'video_file_available' => true,
            'cover_file_available' => true,
        ]);
        Storage::disk('s3')->put($available->video_path, 'video');

        $this->artisan('media:audit')
            ->expectsOutputToContain('Checked 2')
            ->assertSuccessful();

        $available->refresh();
        $missing->refresh();
        $this->assertTrue($available->video_file_available);
        $this->assertTrue($available->cover_file_available);
        $this->assertFalse($missing->video_file_available);
        $this->assertFalse($missing->cover_file_available);
        $this->assertDatabaseHas('sync_changes', [
            'entity_type' => 'video',
            'entity_id' => $missing->id,
            'action' => 'upsert',
        ]);
    }

    public function test_returns_422_when_video_uses_soft_deleted_category(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();
        $category->delete();

        $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-010',
            'product_name' => 'Spidol',
            'normal_price' => 'Rp 8.000',
            'wholesale_price' => 'Rp 7.000',
            'video' => UploadedFile::fake()->create('product.mp4', 10, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }
}
