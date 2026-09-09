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
}
