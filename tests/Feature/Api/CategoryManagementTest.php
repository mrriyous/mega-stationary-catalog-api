<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_categories_with_the_same_name(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/categories', ['name' => 'Alat Tulis'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alat Tulis');
        $this->postJson('/api/categories', ['name' => 'Alat Tulis'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alat Tulis');

        $this->assertSame(2, Category::where('name', 'Alat Tulis')->count());
    }

    public function test_creating_a_category_records_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/categories', ['name' => 'Alat Tulis'])->assertCreated();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'created',
            'subject_type' => Category::class,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_admin_soft_deletes_unused_category(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create(['name' => 'Arsip']);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($category);
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/categories/{$category->id}")
            ->assertNotFound();
    }

    public function test_returns_422_when_category_is_still_used_by_videos(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();
        Video::factory()->for($category)->create();

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori masih digunakan oleh video.');

        $this->assertNotSoftDeleted($category);
    }

    public function test_admin_can_soft_delete_category_after_its_videos_are_soft_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();
        $video = Video::factory()->for($category)->create();

        $this->deleteJson("/api/videos/{$video->id}")->assertNoContent();
        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();

        $this->assertSoftDeleted($category);
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_category_listing_excludes_soft_deleted_videos_from_counts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create(['name' => 'Produk']);
        $kept = Video::factory()->for($category)->create();
        $removed = Video::factory()->for($category)->create();
        $removed->delete();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Produk')
            ->assertJsonPath('data.0.videos_count', 1);

        $this->assertNotSoftDeleted($kept);
    }

    public function test_returns_403_when_regular_user_creates_category(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->postJson('/api/categories', ['name' => 'Promo'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Akses admin diperlukan.');
    }

    public function test_reorder_requires_every_active_category_exactly_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = Category::factory()->create();
        $second = Category::factory()->create();

        $this->postJson('/api/categories/reorder', ['ids' => [$first->id]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Daftar kategori tidak lengkap atau tidak valid.');
        $this->postJson('/api/categories/reorder', ['ids' => [$first->id, $first->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.1']);
        $this->postJson('/api/categories/reorder', ['ids' => [$second->id, $first->id]])
            ->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }
}
