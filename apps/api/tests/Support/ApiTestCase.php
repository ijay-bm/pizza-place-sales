<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The base of every test that calls /api/v1, against the mysql-test database.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Sends the next requests as the SPA does: Sanctum starts a session for a request whose Origin is in
     * config('sanctum.stateful').
     */
    protected function fromTheSpa(): static
    {
        return $this->withHeader('Origin', config('app.url'));
    }
}
