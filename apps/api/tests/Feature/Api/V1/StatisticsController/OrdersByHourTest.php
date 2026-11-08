<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class OrdersByHourTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/orders-by-hour';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_orders_are_counted_by_hour(): void
    {
        $this->statistic()
            ->assertOk()
            ->assertExactJson([
                'data' => [['hour' => 11, 'orders' => 2], ['hour' => 12, 'orders' => 1], ['hour' => 13, 'orders' => 1]],
            ]);
    }

    public function test_the_range_ends_with_its_last_day(): void
    {
        $this->statistic(['ordered_from' => '2015-01-01', 'ordered_to' => '2015-01-01'])
            ->assertOk()
            ->assertJsonCount(3, 'data');
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
