<?php

namespace Tests\Feature\Api\V1\OrderController;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * On the fixtures: order 1 (11:38:36) hawaiian_m, total 13.25; order 2 (11:57:40) five pizzas from the classic,
 * veggie, supreme and chicken categories, total 92.00; order 3 (12:12:28) ital_supr_m and prsc_argla_l, total 37.25;
 * order 17 (13:53:00) calabrese_m and two mediterraneo_m, total 48.25. All on 2015-01-01.
 */
class IndexTest extends ApiTestCase
{
    private const string URI = '/api/v1/orders';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSalesFixtures();
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->getJson(self::URI)->assertUnauthorized();
    }

    public function test_orders_come_newest_first_with_their_totals(): void
    {
        $this->list()
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0', [
                'id' => 17,
                'ordered_at' => '2015-01-01T13:53:00',
                'pizza_count' => 3,
                'total' => '48.25',
            ]);

        $this->assertSame([17, 3, 2, 1], $this->ids($this->list()));
    }

    public function test_orders_are_sorted_by_total(): void
    {
        $this->assertSame([2, 17, 3, 1], $this->ids($this->list(['sort' => '-total'])));
        $this->assertSame([1, 3, 17, 2], $this->ids($this->list(['sort' => 'total'])));
    }

    public function test_orders_are_filtered_by_date_range(): void
    {
        $this->assertSame(
            [17, 3, 2, 1],
            $this->ids($this->list(['filter' => ['ordered_from' => '2015-01-01', 'ordered_to' => '2015-01-01']])),
        );
        $this->assertSame([], $this->ids($this->list(['filter' => ['ordered_from' => '2015-01-02']])));
    }

    public function test_orders_are_filtered_by_category_size_and_pizza_type(): void
    {
        $this->assertSame([17, 2], $this->ids($this->list(['filter' => ['category' => 'veggie']])));
        $this->assertSame([3, 2], $this->ids($this->list(['filter' => ['size' => 'L']])));
        $this->assertSame([1], $this->ids($this->list(['filter' => ['pizza_type' => 'hawaiian']])));
    }

    public function test_pizza_filters_together_describe_one_pizza(): void
    {
        // Order 2 holds a medium classic and large pizzas of other categories: a large classic is in neither order.
        $this->assertSame([], $this->ids($this->list(['filter' => ['size' => 'L', 'category' => 'classic']])));
        $this->assertSame([2, 1], $this->ids($this->list(['filter' => ['size' => 'M', 'category' => 'classic']])));
        $this->assertSame([2], $this->ids($this->list(['filter' => ['search' => 'mushrooms', 'size' => 'M']])));
        $this->assertSame([], $this->ids($this->list(['filter' => ['search' => 'mushrooms', 'size' => 'L']])));
    }

    public function test_a_search_matches_an_order_number_a_pizza_name_or_an_ingredient(): void
    {
        $this->assertSame([17], $this->ids($this->list(['filter' => ['search' => '17']])));
        $this->assertSame([2], $this->ids($this->list(['filter' => ['search' => 'mexicana']])));
        $this->assertSame([17], $this->ids($this->list(['filter' => ['search' => 'Nduja']])));
    }

    public function test_each_orders_lines_are_included_on_request(): void
    {
        $this->list(['include' => 'orderItems.pizza.pizzaType', 'filter' => ['search' => '17']])
            ->assertOk()
            ->assertJsonCount(2, 'data.0.order_items')
            ->assertJsonPath('data.0.order_items.1.pizza.code', 'mediterraneo_m')
            ->assertJsonPath('data.0.order_items.1.pizza.pizza_type.name', 'The Mediterranean Pizza')
            ->assertJsonPath('data.0.order_items.1.line_total', '32.00');

        $this->assertArrayNotHasKey('order_items', $this->list()->json('data.0'));
    }

    public function test_an_unknown_include_is_refused(): void
    {
        $this->list(['include' => 'customer'])->assertBadRequest();
    }

    public function test_a_page_holds_per_page_orders(): void
    {
        $this->list(['per_page' => 2, 'page' => 2])
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_an_unknown_filter_is_refused(): void
    {
        $this->list(['filter' => ['customer' => 'x']])->assertBadRequest();
    }

    public function test_invalid_values_are_refused_by_field(): void
    {
        $this->list(['filter' => ['ordered_from' => '01/01/2015', 'category' => 'dessert'], 'per_page' => 101])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['filter.ordered_from', 'filter.category', 'per_page']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function list(array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->create(), 'web')
            ->fromTheSpa()
            ->getJson(self::URI . '?' . http_build_query($query));
    }

    /**
     * @return list<int>
     */
    private function ids(TestResponse $response): array
    {
        return array_column($response->assertOk()->json('data'), 'id');
    }
}
