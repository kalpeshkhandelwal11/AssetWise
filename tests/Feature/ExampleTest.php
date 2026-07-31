<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_returns_successful_response(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
