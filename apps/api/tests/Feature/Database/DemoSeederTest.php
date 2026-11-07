<?php

namespace Tests\Feature\Database;

use Database\Seeders\Local\DemoSeeder;
use Tests\Support\ApiTestCase;

class DemoSeederTest extends ApiTestCase
{
    public function test_the_demo_account_can_log_in(): void
    {
        $this->seed(DemoSeeder::class);

        $this->fromTheSpa()
            ->postJson('/api/v1/auth/login', ['email' => 'demo@pizza-place-sales.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Demo User');
    }
}
