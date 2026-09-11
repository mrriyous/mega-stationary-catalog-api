<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_user_price_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/users', [
            'name' => 'Toko Mawar',
            'username' => 'mawar',
            'password' => 'secret123',
            'role' => 'user',
            'normal_price_access' => false,
            'wholesale_price_access' => true,
        ])->assertCreated()
            ->assertJsonPath('data.normal_price_access', false)
            ->assertJsonPath('data.wholesale_price_access', true);

        $userId = $created->json('data.id');
        $this->putJson("/api/users/{$userId}", [
            'name' => 'Toko Mawar',
            'username' => 'mawar',
            'role' => 'user',
            'normal_price_access' => true,
            'wholesale_price_access' => false,
        ])->assertOk()
            ->assertJsonPath('data.normal_price_access', true)
            ->assertJsonPath('data.wholesale_price_access', false);
    }

    public function test_me_returns_current_price_access_for_periodic_refresh(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'normal_price_access' => false,
            'wholesale_price_access' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.normal_price_access', false)
            ->assertJsonPath('user.wholesale_price_access', true);
    }

    public function test_regular_user_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Akses admin diperlukan.');
    }

    public function test_regular_user_must_have_exactly_one_price_access(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/users', [
            'name' => 'Tanpa Harga',
            'username' => 'tanpa-harga',
            'password' => 'secret123',
            'role' => 'user',
            'normal_price_access' => false,
            'wholesale_price_access' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pengguna harus memiliki tepat satu akses harga: Normal atau Grosir.');

        $this->postJson('/api/users', [
            'name' => 'Dua Harga',
            'username' => 'dua-harga',
            'password' => 'secret123',
            'role' => 'user',
            'normal_price_access' => true,
            'wholesale_price_access' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pengguna harus memiliki tepat satu akses harga: Normal atau Grosir.');
    }
}
