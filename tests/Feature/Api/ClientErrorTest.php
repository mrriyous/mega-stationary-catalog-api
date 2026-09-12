<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_device_can_report_errors_idempotently(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $reference = '123e4567-e89b-42d3-a456-426614174000';
        $payload = ['errors' => [[
            'reference' => $reference,
            'type' => 'DatabaseException',
            'message' => 'A safe client error',
            'stack_trace' => '#0 LocalDatabase.sync',
            'context' => 'catalog_sync',
            'occurred_at' => now()->toIso8601String(),
            'platform' => 'android',
        ]]];

        $this->postJson('/api/client-errors', $payload)
            ->assertAccepted()
            ->assertJsonPath('accepted.0', $reference);
        $this->postJson('/api/client-errors', $payload)->assertAccepted();

        $this->assertDatabaseCount('error_logs', 1);
        $this->assertDatabaseHas('error_logs', [
            'reference' => $reference,
            'method' => 'CLIENT',
            'exception_class' => 'DatabaseException',
            'message' => 'A safe client error',
        ]);
    }

    public function test_client_error_reporting_requires_authentication(): void
    {
        $this->postJson('/api/client-errors', ['errors' => []])
            ->assertUnauthorized();
    }
}
