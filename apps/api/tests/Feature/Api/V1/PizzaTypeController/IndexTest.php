<?php

namespace Tests\Feature\Api\V1\PizzaTypeController;

use App\Models\User;
use Tests\Support\ApiTestCase;

class IndexTest extends ApiTestCase
{
    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson('/api/v1/pizza-types')->assertUnauthorized();
    }

    public function test_every_pizza_type_comes_by_name_with_its_sizes(): void
    {
        $this->importSalesFixtures();

        $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/pizza-types')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.name', 'The Calabrese Pizza')
            ->assertJsonPath('data.0.category', 'supreme')
            ->assertJsonPath('data.0.pizzas.*.size', ['S', 'M', 'L']);
    }

    public function test_each_pizza_type_comes_with_its_ingredients_by_name(): void
    {
        $this->importSalesFixtures();

        $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/pizza-types')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'calabrese')
            ->assertJsonPath('data.0.ingredients', [
                "\u{2018}Nduja Salami",
                'Friggitello Peppers',
                'Garlic',
                'Pancetta',
                'Red Onions',
                'Tomatoes',
            ]);
    }
}
