<?php

namespace App\Queries;

use App\Models\Order;
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
        return QueryBuilder::for($this->base())
            ->allowedFilters(...$this->filters())
            ->allowedIncludes(...self::INCLUDES)
            ->allowedSorts('ordered_at', 'total', 'id')
            ->defaultSort('-ordered_at', '-id');
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
     * @return list<AllowedFilter>
     */
    private function filters(): array
    {
        return [
            AllowedFilter::callback('ordered_from', function (Builder $query, string $date): void {
                $query->where('ordered_at', '>=', $date);
            }),
            AllowedFilter::callback('ordered_to', function (Builder $query, string $date): void {
                $query->where('ordered_at', '<', CarbonImmutable::parse($date)->addDay()->toDateString());
            }),
            AllowedFilter::callback('category', function (Builder $query, string $category): void {
                $query->whereHas(
                    'orderItems.pizza.pizzaType',
                    fn (Builder $pizzaTypes) => $pizzaTypes->where('category', $category),
                );
            }),
            AllowedFilter::callback('size', function (Builder $query, string $size): void {
                $query->whereHas('orderItems.pizza', fn (Builder $pizzas) => $pizzas->where('size', $size));
            }),
            AllowedFilter::callback('pizza_type', function (Builder $query, string $code): void {
                $query->whereHas(
                    'orderItems.pizza.pizzaType',
                    fn (Builder $pizzaTypes) => $pizzaTypes->where('code', $code),
                );
            }),
            AllowedFilter::callback('search', $this->search(...)),
        ];
    }

    /**
     * Matches an order whose number is the search, or that holds a pizza whose name or one of whose ingredients
     * contains it.
     *
     * @param  Builder<Order>  $query
     * @param  string|list<string>  $value  a list when the search held a comma, which the query builder splits on
     */
    private function search(Builder $query, string|array $value): void
    {
        $search = trim(is_array($value) ? implode(',', $value) : $value);
        // LIKE reads \, % and _ as instructions; a backslash before each makes the search match them as typed.
        $pattern = '%' . addcslashes($search, '\\%_') . '%';

        $query->where(function (Builder $query) use ($search, $pattern): void {
            if (ctype_digit($search)) {
                $query->whereKey((int) $search);
            }

            $query->orWhereHas('orderItems.pizza.pizzaType', function (Builder $pizzaTypes) use ($pattern): void {
                $pizzaTypes
                    ->whereLike('name', $pattern)
                    ->orWhereHas('ingredients', fn (Builder $ingredients) => $ingredients->whereLike('name', $pattern));
            });
        });
    }
}
