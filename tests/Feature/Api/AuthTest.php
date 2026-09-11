<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_login_and_receive_a_device_token(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'password',
            'device_name' => 'test-phone',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username', 'role', 'normal_price_access', 'wholesale_price_access']]);
    }

    public function test_returns_422_when_credentials_are_invalid(): void
    {
        User::factory()->create(['username' => 'user']);

        $this->postJson('/api/login', [
            'username' => 'user',
            'password' => 'wrong-password',
            'device_name' => 'test-phone',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Username atau kata sandi salah.');
    }

    public function test_returns_422_when_soft_deleted_user_logs_in(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'password' => 'password',
        ]);
        $user->delete();

        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'password',
            'device_name' => 'test-phone',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Username atau kata sandi salah.');
    }

    public function test_usernames_are_not_required_to_be_unique(): void
    {
        User::factory()->create(['username' => 'kasir']);
        User::factory()->create(['username' => 'kasir', 'email' => 'kasir-dua@mega.test']);

        $this->assertSame(2, User::where('username', 'kasir')->count());
    }

    public function test_logout_returns_indonesian_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil keluar.');
    }
}
