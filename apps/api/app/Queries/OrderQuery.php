<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\Pizza;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * How orders are read: the list behind GET /api/v1/orders and the single order behind GET /api/v1/orders/{orderId},
 * both built from the same base query.
 */
class OrderQuery
{
    private const array INCLUDES = ['orderItems.pizza.pizzaType'];

    /**
     * Returns the orders with their totals, narrowed by the request's filter[...] parameters, sorted by its sort
     * parameter, newest first by default, and with each order's lines when it asks include=orderItems.pizza.pizzaType.
     * A filter, sort or include not listed here is refused with a 400.
     *
     * @return QueryBuilder<Order>
     */
    public function index(): QueryBuilder
    {
        $pizzas = Pizza::query();

        $orders = QueryBuilder::for($this->base())
            ->allowedFilters(...$this->filters($pizzas))
            ->allowedIncludes(...self::INCLUDES)
            ->allowedSorts('ordered_at', 'total', 'id')
            ->defaultSort('-ordered_at', '-id');

        // The pizza filters, applied by allowedFilters() above, only narrowed $pizzas, so together they describe one
        // pizza: size L with category classic is a large classic pizza, not any large one beside any classic one.
        if ($pizzas->getQuery()->wheres !== []) {
            $this->holdingAnyOf($orders->getEloquentBuilder(), $pizzas);
        }

        return $orders;
    }

    /**
     * Returns one order with its totals, and with its lines when the request asks include=orderItems.pizza.pizzaType.
     *
     * @throws ModelNotFoundException<Order>
     */
    public function show(int $orderId): Order
    {
        return QueryBuilder::for($this->base()->whereKey($orderId))
            ->allowedIncludes(...self::INCLUDES)
            ->firstOrFail();
    }

    /**
     * Returns every order with its totals; the list and a single order are both read from it.
     *
     * @return Builder<Order>
     */
    private function base(): Builder
    {
        return Order::query()->withTotals();
    }

    /**
     * Returns the filters. The ones on the order narrow the orders; the ones on a pizza narrow $pizzas, which index()
     * matches the orders against once.
     *
     * @param  Builder<Pizza>  $pizzas
     * @return list<AllowedFilter>
     */
    private function filters(Builder $pizzas): array
    {
        return [
            AllowedFilter::callback('ordered_from', function (Builder $orders, string $date): void {
                $orders->where('ordered_at', '>=', $date);
            }),
            AllowedFilter::callback('ordered_to', function (Builder $orders, string $date): void {
                $orders->where('ordered_at', '<', CarbonImmutable::parse($date)->addDay()->toDateString());
            }),
            AllowedFilter::callback('category', function (Builder $orders, string $category) use ($pizzas): void {
                $pizzas->whereHas('pizzaType', fn (Builder $pizzaTypes) => $pizzaTypes->where('category', $category));
            }),
            AllowedFilter::callback('size', function (Builder $orders, string $size) use ($pizzas): void {
                $pizzas->where('size', $size);
            }),
            AllowedFilter::callback('pizza_type', function (Builder $orders, string $code) use ($pizzas): void {
                $pizzas->whereHas('pizzaType', fn (Builder $pizzaTypes) => $pizzaTypes->where('code', $code));
            }),
            AllowedFilter::callback('search', function (Builder $orders, string|array $value) use ($pizzas): void {
                $this->search($orders, $pizzas, $value);
            }),
        ];
    }

    /**
     * Matches an order by its number when the search is all digits, since no pizza name or ingredient has a digit;
     * any other search narrows $pizzas to those whose name or one of whose ingredients contains it.
     *
     * @param  Builder<Order>  $orders
     * @param  Builder<Pizza>  $pizzas
     * @param  string|list<string>  $value  a list when the search held a comma, which the query builder splits on
     */
    private function search(Builder $orders, Builder $pizzas, string|array $value): void
    {
        $search = trim(is_array($value) ? implode(',', $value) : $value);

        if (ctype_digit($search)) {
            $orders->whereKey((int) $search);

            return;
        }

        // LIKE reads \, % and _ as instructions; a backslash before each makes the search match them as typed.
        $pattern = '%' . addcslashes($search, '\\%_') . '%';

        $pizzas->whereHas('pizzaType', function (Builder $pizzaTypes) use ($pattern): void {
            $pizzaTypes
                ->whereLike('name', $pattern)
                ->orWhereHas('ingredients', fn (Builder $ingredients) => $ingredients->whereLike('name', $pattern));
        });
    }

    /**
     * Narrows the orders to those holding at least one of the pizzas the given query selects. The pizza ids are read
     * first, from the small pizzas table, and the orders are then matched on the indexed order_items.pizza_id: as
     * nested whereHas filters over the order lines, a search with a category took MySQL over three seconds.
     *
     * @param  Builder<Order>  $orders
     * @param  Builder<Pizza>  $pizzas
     */
    private function holdingAnyOf(Builder $orders, Builder $pizzas): void
    {
        $pizzaIds = $pizzas->pluck('id');

        $orders->whereHas('orderItems', fn (Builder $orderItems) => $orderItems->whereIn('pizza_id', $pizzaIds));
    }
}
