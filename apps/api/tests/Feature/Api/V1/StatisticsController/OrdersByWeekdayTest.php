<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class OrdersByWeekdayTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/orders-by-weekday';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_orders_are_counted_by_iso_weekday(): void
    {
        // 1 January 2015 was a Thursday, ISO weekday 4.
        $this->statistic()
            ->assertOk()
            ->assertExactJson(['data' => [['weekday' => 4, 'orders' => 4]]]);
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
