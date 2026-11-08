<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pizza;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class RevenueByMonthTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/revenue-by-month';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_revenue_is_grouped_by_month(): void
    {
        $this->statistic()
            ->assertOk()
            ->assertExactJson(['data' => [['month' => '2015-01', 'revenue' => '190.75', 'orders' => 4]]]);
    }

    public function test_every_month_between_the_first_and_the_last_is_listed_across_years(): void
    {
        // A hawaiian_m at 13.25 in March 2015 and in January 2016: February 2015 and April to December 2015 sold nothing.
        $pizzaId = Pizza::query()->where('code', 'hawaiian_m')->value('id');
        foreach ([[90001, '2015-03-10 12:00:00'], [90002, '2016-01-05 12:00:00']] as [$orderId, $orderedAt]) {
            Order::query()->create(['id' => $orderId, 'ordered_at' => $orderedAt]);
            OrderItem::query()->create([
                'id' => $orderId,
                'order_id' => $orderId,
                'pizza_id' => $pizzaId,
                'quantity' => 1,
            ]);
        }

        $months = $this->statistic()->assertOk()->assertJsonCount(13, 'data')->json('data');

        $this->assertSame(['month' => '2015-01', 'revenue' => '190.75', 'orders' => 4], $months[0]);
        $this->assertSame(['month' => '2015-02', 'revenue' => '0.00', 'orders' => 0], $months[1]);
        $this->assertSame(['month' => '2015-03', 'revenue' => '13.25', 'orders' => 1], $months[2]);
        $this->assertSame(['month' => '2016-01', 'revenue' => '13.25', 'orders' => 1], $months[12]);
    }

    public function test_the_months_of_a_date_range_are_listed_even_without_orders(): void
    {
        $this->statistic(['ordered_from' => '2015-01-01', 'ordered_to' => '2015-03-31'])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['month' => '2015-01', 'revenue' => '190.75', 'orders' => 4],
                    ['month' => '2015-02', 'revenue' => '0.00', 'orders' => 0],
                    ['month' => '2015-03', 'revenue' => '0.00', 'orders' => 0],
                ],
            ]);
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
