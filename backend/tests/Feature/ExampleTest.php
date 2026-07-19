<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `/` is now the SPA fallback (it serves the built Vue app, which only exists
     * in a deployed build), so smoke-test the public API instead.
     */
    public function test_the_public_api_responds(): void
    {
        $this->getJson('/api/products')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/categories')->assertOk();
    }
}
