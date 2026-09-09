<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_upload_video_and_listing_reports_server_data(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Promo', 'sort_order' => 0]);

        $response = $this->post('/api/videos', [
            'category_id' => $category->id,
            'product_code' => 'PRD-001',
            'product_name' => 'Buku Tulis',
            'normal_price' => 'Rp 20.000',
            'wholesale_price' => 'Rp 17.000',
            'video' => UploadedFile::fake()->create('product.mp4', 250, 'video/mp4'),
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.product_code', 'PRD-001')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonStructure(['data' => ['video_url', 'cover_url', 'video_size_bytes']]);
        $this->assertDatabaseHas('videos', [
            'product_code' => 'PRD-001',
            'category_id' => $category->id,
        ]);
        $video = Video::where('product_code', 'PRD-001')->firstOrFail();
        Storage::disk('local')->assertExists($video->video_path);
        Storage::disk('local')->assertExists($video->cover_path);

        $this->getJson('/api/videos')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_name', 'Buku Tulis');
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
        ], ['Accept' => 'application/json'])->assertForbidden();
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
}
