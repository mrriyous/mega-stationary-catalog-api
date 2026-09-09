<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\SyncChange;
use App\Models\User;
use App\Support\SyncPayload;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_device_receives_cursor_based_changes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Produk', 'sort_order' => 0]);
        $change = SyncChange::create([
            'entity_type' => 'category',
            'entity_id' => $category->id,
            'action' => 'upsert',
            'payload' => SyncPayload::category($category),
        ]);

        $this->getJson('/api/sync?cursor=0')->assertOk()
            ->assertJsonPath('changes.0.cursor', $change->id)
            ->assertJsonPath('changes.0.payload.name', 'Produk')
            ->assertJsonPath('next_cursor', $change->id)
            ->assertJsonPath('has_more', false);
    }

    public function test_returns_401_when_sync_has_no_token(): void
    {
        $this->getJson('/api/sync?cursor=0')->assertUnauthorized();
    }

    public function test_returns_json_401_for_api_request_without_accept_header(): void
    {
        $this->get('/api/categories')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_more_than_200_changes_are_exposed_in_cursor_batches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        for ($index = 1; $index <= 201; $index++) {
            SyncChange::create([
                'entity_type' => 'category',
                'entity_id' => $index,
                'action' => 'delete',
                'payload' => ['id' => $index],
            ]);
        }

        $firstBatch = $this->getJson('/api/sync?cursor=0')
            ->assertOk()
            ->assertJsonCount(200, 'changes')
            ->assertJsonPath('has_more', true);

        $cursor = $firstBatch->json('next_cursor');
        $this->getJson("/api/sync?cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(1, 'changes')
            ->assertJsonPath('changes.0.entity_id', 201)
            ->assertJsonPath('has_more', false);
    }
}
