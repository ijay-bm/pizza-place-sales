<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class SummaryTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/summary';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_the_summary_adds_up_the_orders(): void
    {
        $this->statistic()
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'revenue' => '190.75',
                    'orders' => 4,
                    'pizzas_sold' => 11,
                    'average_order_value' => '47.69',
                    'average_pizzas_per_order' => '2.75',
                ],
            ]);
    }

    public function test_a_range_with_no_orders_is_all_zeros(): void
    {
        $this->statistic(['ordered_from' => '2015-01-02'])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'revenue' => '0.00',
                    'orders' => 0,
                    'pizzas_sold' => 0,
                    'average_order_value' => '0.00',
                    'average_pizzas_per_order' => '0.00',
                ],
            ]);
    }

    public function test_an_unknown_filter_or_a_bad_date_is_refused(): void
    {
        $this->statistic(['customer' => 'x', 'ordered_to' => '01/01/2015'])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['filter', 'filter.ordered_to']);
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
