<?php

namespace Database\Seeders\Local;

use App\Imports\SalesDataImporter;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local only: the demo data a fresh local database is seeded with, never run on a release.
 */
class DemoSeeder extends Seeder
{
    /**
     * Creates the demo account, then imports the full dataset in pizza-place-sales/ through the same importer
     * POST /api/v1/imports uses.
     */
    public function run(SalesDataImporter $salesDataImporter): void
    {
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@pizza-place-sales.test',
        ]);

        $salesDataImporter->import(
            pizzaTypesPath: __DIR__ . '/pizza-place-sales/pizza_types.csv',
            pizzasPath: __DIR__ . '/pizza-place-sales/pizzas.csv',
            ordersPath: __DIR__ . '/pizza-place-sales/orders.csv',
            orderDetailsPath: __DIR__ . '/pizza-place-sales/order_details.csv',
        );
    }
}
