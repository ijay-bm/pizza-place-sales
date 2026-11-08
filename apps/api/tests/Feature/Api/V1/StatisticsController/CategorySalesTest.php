<?php

namespace Tests\Feature\Api\V1\StatisticsController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: orders 1, 2, 3 and 17, all on Thursday 1 January 2015, totalling 190.75 for 11 pizzas.
 */
class CategorySalesTest extends ApiTestCase
{
    private const string URI = '/api/v1/statistics/category-sales';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_categories_are_ranked_by_revenue(): void
    {
        $this->statistic()
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['category' => 'supreme', 'quantity' => 4, 'revenue' => '74.25'],
                    ['category' => 'veggie', 'quantity' => 4, 'revenue' => '66.50'],
                    ['category' => 'classic', 'quantity' => 2, 'revenue' => '29.25'],
                    ['category' => 'chicken', 'quantity' => 1, 'revenue' => '20.75'],
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
