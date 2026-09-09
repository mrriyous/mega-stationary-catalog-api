<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username', 'role']]);
    }

    public function test_returns_422_when_credentials_are_invalid(): void
    {
        User::factory()->create(['username' => 'user']);

        $this->postJson('/api/login', [
            'username' => 'user',
            'password' => 'wrong-password',
            'device_name' => 'test-phone',
        ])->assertUnprocessable();
    }
}
