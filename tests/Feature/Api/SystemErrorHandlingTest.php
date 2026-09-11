<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SystemErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_errors_return_safe_message_and_are_stored(): void
    {
        Route::get('/api/testing/system-error', function () {
            throw new RuntimeException('Sensitive database and SQL details');
        });

        $response = $this->getJson('/api/testing/system-error')
            ->assertInternalServerError()
            ->assertJsonPath('message', 'Terjadi kesalahan pada server. Silakan coba lagi.')
            ->assertJsonStructure(['error_id'])
            ->assertJsonMissing(['Sensitive database and SQL details']);

        $this->assertDatabaseHas('error_logs', [
            'reference' => $response->json('error_id'),
            'method' => 'GET',
            'path' => 'api/testing/system-error',
            'status_code' => 500,
            'exception_class' => RuntimeException::class,
            'message' => 'Sensitive database and SQL details',
        ]);
    }

    public function test_validation_errors_keep_their_actionable_details(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password'])
            ->assertJsonMissing(['error_id']);
    }
}
