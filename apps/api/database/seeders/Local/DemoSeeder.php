<?php

namespace Database\Seeders\Local;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local only: the demo data a fresh local database is seeded with, never run on a release.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@pizza-place-sales.test',
        ]);
    }
}
