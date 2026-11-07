<?php

namespace Tests\Support;

use App\Imports\SalesDataImporter;
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

    /**
     * Imports tests/Fixtures/imports/valid: orders 1, 2, 3 and 17, their ten lines, and the ten pizza types and
     * thirty pizzas those lines name.
     */
    protected function importSalesFixtures(): void
    {
        app(SalesDataImporter::class)->import(
            pizzaTypesPath: base_path('tests/Fixtures/imports/valid/pizza_types.csv'),
            pizzasPath: base_path('tests/Fixtures/imports/valid/pizzas.csv'),
            ordersPath: base_path('tests/Fixtures/imports/valid/orders.csv'),
            orderDetailsPath: base_path('tests/Fixtures/imports/valid/order_details.csv'),
        );
    }
}
