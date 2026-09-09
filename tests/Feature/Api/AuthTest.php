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
            'email' => 'admin@mega.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@mega.test',
            'password' => 'password',
            'device_name' => 'test-phone',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_returns_422_when_credentials_are_invalid(): void
    {
        User::factory()->create(['email' => 'user@mega.test']);

        $this->postJson('/api/login', [
            'email' => 'user@mega.test',
            'password' => 'wrong-password',
            'device_name' => 'test-phone',
        ])->assertUnprocessable();
    }
}
