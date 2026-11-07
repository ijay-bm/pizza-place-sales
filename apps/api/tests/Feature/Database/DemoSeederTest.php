<?php

namespace Tests\Feature\Database;

use Database\Seeders\Local\DemoSeeder;
use Tests\Support\ApiTestCase;

/**
 * Seeds the full dataset, so it is the slowest test in the suite.
 */
class DemoSeederTest extends ApiTestCase
{
    public function test_the_demo_account_and_the_full_dataset_are_seeded(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('pizza_types', 32);
        $this->assertDatabaseCount('pizzas', 96);
        $this->assertDatabaseCount('orders', 21350);
        $this->assertDatabaseCount('order_items', 48620);

        $this->fromTheSpa()
            ->postJson('/api/v1/auth/login', ['email' => 'demo@pizza-place-sales.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Demo User');
    }
}
