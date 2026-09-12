<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_api_has_no_public_root_page(): void
    {
        $this->get('/')->assertNotFound();
    }
}
