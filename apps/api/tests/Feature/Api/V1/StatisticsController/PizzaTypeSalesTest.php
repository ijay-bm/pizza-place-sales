<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class PizzaTypeSalesTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/pizza-type-sales';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_every_pizza_type_is_ranked_by_revenue_including_those_that_sold_nothing(): void
    {
        $pizzaTypeSales = $this->statistic()->assertOk()->assertJsonCount(10, 'data')->json('data');

        $this->assertSame(
            [
                'code' => 'ital_supr',
                'name' => 'The Italian Supreme Pizza',
                'category' => 'supreme',
                'quantity' => 2,
                'revenue' => '37.25',
            ],
            $pizzaTypeSales[0],
        );
        // A tie on revenue is ordered by name: Prosciutto before Thai.
        $this->assertSame(['prsc_argla', 'thai_ckn'], [$pizzaTypeSales[2]['code'], $pizzaTypeSales[3]['code']]);
        $this->assertSame(
            ['code' => 'pep_msh_pep', 'quantity' => 0, 'revenue' => '0.00'],
            [
                'code' => $pizzaTypeSales[9]['code'],
                'quantity' => $pizzaTypeSales[9]['quantity'],
                'revenue' => $pizzaTypeSales[9]['revenue'],
            ],
        );
    }

    /**
     * @param  array<string, string>  $filter
     */
    private function statistic(array $filter = []): TestResponse
    {
        return $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson(self::URI . '?' . http_build_query(['filter' => $filter]));
    }
}
