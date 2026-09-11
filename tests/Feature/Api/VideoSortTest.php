<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\SyncChange;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSortData;
use App\Services\VideoSortService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reorders_category_without_updating_video_records(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create(['sort_order' => 2]);
        $first = Video::factory()->for($category)->create(['product_name' => 'First']);
        $second = Video::factory()->for($category)->create(['product_name' => 'Second']);
        $service = app(VideoSortService::class);
        DB::transaction(function () use ($service, $first, $second) {
            $service->createFor($first);
            $service->createFor($second);
        });
        $videoUpdatedAt = $first->updated_at;
        $cursor = SyncChange::max('id');

        $this->postJson("/api/categories/{$category->id}/videos/reorder", [
            'ids' => [$second->id, $first->id],
        ])->assertOk();

        $this->getJson("/api/categories/{$category->id}/videos/order")
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.product_name', 'Second')
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.product_name', 'First');

        $this->assertDatabaseHas('video_sort_data', [
            'video_id' => $second->id,
            'category_id' => $category->id,
            'category_order' => 2,
            'video_order' => 0,
        ]);
        $this->assertDatabaseHas('video_sort_data', [
            'video_id' => $first->id,
            'video_order' => 1,
        ]);
        $this->assertSame($videoUpdatedAt->toISOString(), $first->fresh()->updated_at->toISOString());

        $this->getJson("/api/sync?cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(2, 'changes')
            ->assertJsonPath('changes.0.entity_type', 'video_sort');
    }

    public function test_reorder_rejects_incomplete_or_cross_category_video_ids(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $first = Video::factory()->for($category)->create();
        $second = Video::factory()->for($category)->create();
        $foreign = Video::factory()->for($other)->create();

        $this->postJson("/api/categories/{$category->id}/videos/reorder", [
            'ids' => [$first->id],
        ])->assertUnprocessable();
        $this->postJson("/api/categories/{$category->id}/videos/reorder", [
            'ids' => [$first->id, $foreign->id],
        ])->assertUnprocessable();
        $this->postJson("/api/categories/{$category->id}/videos/reorder", [
            'ids' => [$first->id, $second->id, $second->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['ids.2']);
    }

    public function test_category_reorder_updates_sort_projection_in_code(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $firstCategory = Category::factory()->create(['sort_order' => 0]);
        $secondCategory = Category::factory()->create(['sort_order' => 1]);
        $video = Video::factory()->for($firstCategory)->create();
        DB::transaction(fn () => app(VideoSortService::class)->createFor($video));

        $this->postJson('/api/categories/reorder', [
            'ids' => [$secondCategory->id, $firstCategory->id],
        ])->assertOk();

        $this->assertDatabaseHas('video_sort_data', [
            'video_id' => $video->id,
            'category_order' => 1,
        ]);
    }

    public function test_video_delete_explicitly_removes_all_duplicate_sort_rows(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();
        $video = Video::factory()->for($category)->create();
        VideoSortData::insert([
            ['video_id' => $video->id, 'category_id' => $category->id, 'category_order' => 0, 'video_order' => 0],
            ['video_id' => $video->id, 'category_id' => $category->id, 'category_order' => 0, 'video_order' => 1],
        ]);

        $this->deleteJson("/api/videos/{$video->id}")->assertNoContent();

        $this->assertDatabaseMissing('video_sort_data', ['video_id' => $video->id]);
    }

    public function test_regular_user_cannot_reorder_videos(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $category = Category::factory()->create();

        $this->postJson("/api/categories/{$category->id}/videos/reorder", ['ids' => []])
            ->assertForbidden()
            ->assertJsonPath('message', 'Akses admin diperlukan.');
    }

    public function test_bootstrap_includes_lightweight_video_sort_metadata(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $category = Category::factory()->create(['sort_order' => 3]);
        $video = Video::factory()->for($category)->create();
        DB::transaction(fn () => app(VideoSortService::class)->createFor($video));

        $this->getJson('/api/sync/bootstrap?after_video_id=0')
            ->assertOk()
            ->assertJsonPath('video_sort_data.0.video_id', $video->id)
            ->assertJsonPath('video_sort_data.0.category_id', $category->id)
            ->assertJsonPath('video_sort_data.0.category_order', 3)
            ->assertJsonPath('video_sort_data.0.video_order', 0);
    }

    public function test_repair_command_replaces_duplicates_and_appends_missing_videos(): void
    {
        $category = Category::factory()->create(['sort_order' => 1]);
        $first = Video::factory()->for($category)->create();
        $second = Video::factory()->for($category)->create();
        VideoSortData::insert([
            ['video_id' => $first->id, 'category_id' => $category->id, 'category_order' => 1, 'video_order' => 4],
            ['video_id' => $first->id, 'category_id' => $category->id, 'category_order' => 1, 'video_order' => 7],
        ]);

        $this->artisan('video-sort:repair')->assertSuccessful();

        $this->assertSame(1, VideoSortData::where('video_id', $first->id)->count());
        $this->assertSame(
            [0, 1],
            VideoSortData::where('category_id', $category->id)->orderBy('video_order')->pluck('video_order')->all(),
        );
        $this->assertDatabaseHas('video_sort_data', ['video_id' => $second->id]);
    }
}
