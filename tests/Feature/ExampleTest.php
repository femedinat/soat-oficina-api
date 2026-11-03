<?php

declare(strict_types=1);

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_root_to_api_docs(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/docs/api');
    }
}
