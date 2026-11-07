<?php

namespace Tests\Feature\Api\V1\OrderController;

use App\Models\User;
use Tests\Support\ApiTestCase;

class ShowTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson('/api/v1/orders/17')->assertUnauthorized();
    }

    public function test_an_order_comes_with_its_lines_and_totals(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/orders/17?include=orderItems.pizza.pizzaType')
            ->assertOk()
            ->assertJsonPath('data.total', '48.25')
            ->assertJsonPath('data.pizza_count', 3)
            ->assertJsonCount(2, 'data.order_items')
            ->assertJsonPath('data.order_items.1', [
                'id' => 49,
                'quantity' => 2,
                'pizza' => [
                    'code' => 'mediterraneo_m',
                    'size' => 'M',
                    'price' => '16.00',
                    'pizza_type' => [
                        'code' => 'mediterraneo',
                        'name' => 'The Mediterranean Pizza',
                        'category' => 'veggie',
                    ],
                ],
                'line_total' => '32.00',
            ]);
    }

    public function test_without_the_include_an_order_comes_without_its_lines(): void
    {
        $order = $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/orders/17')
            ->assertOk()
            ->assertJsonPath('data.total', '48.25')
            ->json('data');

        $this->assertArrayNotHasKey('order_items', $order);
    }

    public function test_an_unknown_include_is_refused(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/orders/17?include=customer')
            ->assertBadRequest();
    }

    public function test_an_unknown_order_is_not_found(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson('/api/v1/orders/999')
            ->assertNotFound();
    }
}
