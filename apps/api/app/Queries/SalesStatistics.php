<?php

namespace App\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind GET /api/v1/statistics/*, each over the orders placed between two dates, both included; a
 * missing date leaves that end of the range open. Revenue is quantity times the pizza's price, as decimal strings.
 */
class SalesStatistics
{
    public function __construct(
        private readonly ?string $orderedFrom = null,
        private readonly ?string $orderedTo = null,
    ) {}

    /**
     * Returns the revenue, the orders, the pizzas sold and the averages per order, rounded to cents by MySQL; with no
     * orders in the range, every figure is zero.
     *
     * @return array{revenue: string, orders: int, pizzas_sold: int, average_order_value: string, average_pizzas_per_order: string}
     */
    public function summary(): array
    {
        $summary = $this->soldPizzas()
            ->selectRaw('coalesce(sum(order_items.quantity * pizzas.price), 0) as revenue')
            ->selectRaw('count(distinct orders.id) as orders')
            ->selectRaw('coalesce(sum(order_items.quantity), 0) as pizzas_sold')
            ->selectRaw(
                'coalesce(round(sum(order_items.quantity * pizzas.price) / count(distinct orders.id), 2), 0) as average_order_value',
            )
            ->selectRaw(
                'coalesce(round(sum(order_items.quantity) / count(distinct orders.id), 2), 0) as average_pizzas_per_order',
            )
            ->first();

        return [
            'revenue' => $summary->revenue,
            'orders' => (int) $summary->orders,
            'pizzas_sold' => (int) $summary->pizzas_sold,
            'average_order_value' => $summary->average_order_value,
            'average_pizzas_per_order' => $summary->average_pizzas_per_order,
        ];
    }

    /**
     * Returns every month from the range's first to its last, oldest first, a month that sold nothing with zeros, so a
     * chart shows the gap instead of closing it up. An open end of the range is the first or last month with an order.
     *
     * @return list<array{month: string, revenue: string, orders: int}>
     */
    public function revenueByMonth(): array
    {
        $salesByMonth = $this->soldPizzas()
            ->selectRaw('date_format(orders.ordered_at, \'%Y-%m\') as month')
            ->selectRaw('sum(order_items.quantity * pizzas.price) as revenue')
            ->selectRaw('count(distinct orders.id) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $firstMonth = $this->orderedFrom ?? ($salesByMonth->keys()->first() ?? $this->orderedTo);
        $lastMonth = $this->orderedTo ?? ($salesByMonth->keys()->last() ?? $this->orderedFrom);

        if ($firstMonth === null || $lastMonth === null) {
            return [];
        }

        $months = [];
        $lastMonthStart = CarbonImmutable::parse($lastMonth)->startOfMonth();

        for (
            $month = CarbonImmutable::parse($firstMonth)->startOfMonth();
            $month <= $lastMonthStart;
            $month = $month->addMonth()
        ) {
            $sales = $salesByMonth->get($month->format('Y-m'));

            $months[] = [
                'month' => $month->format('Y-m'),
                'revenue' => $sales->revenue ?? '0.00',
                'orders' => (int) ($sales->orders ?? 0),
            ];
        }

        return $months;
    }

    /**
     * @return list<array{hour: int, orders: int}>
     */
    public function ordersByHour(): array
    {
        return $this->placedOrders()
            ->selectRaw('hour(orders.ordered_at) as hour')
            ->selectRaw('count(*) as orders')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn (object $hour): array => ['hour' => (int) $hour->hour, 'orders' => (int) $hour->orders])
            ->all();
    }

    /**
     * Counts the orders per day of the week, numbered 1 for Monday to 7 for Sunday, as ISO 8601 does.
     *
     * @return list<array{weekday: int, orders: int}>
     */
    public function ordersByWeekday(): array
    {
        return $this->placedOrders()
            // MySQL's weekday() counts Monday as 0.
            ->selectRaw('weekday(orders.ordered_at) + 1 as weekday')
            ->selectRaw('count(*) as orders')
            ->groupBy('weekday')
            ->orderBy('weekday')
            ->get()
            ->map(
                fn (object $weekday): array => [
                    'weekday' => (int) $weekday->weekday,
                    'orders' => (int) $weekday->orders,
                ],
            )
            ->all();
    }

    /**
     * Returns every pizza type with what it sold, the best-selling first; a type that sold nothing is listed with
     * zeros, so the worst sellers include it.
     *
     * @return list<array{code: string, name: string, category: string, quantity: int, revenue: string}>
     */
    public function pizzaTypeSales(): array
    {
        $salesByPizzaType = $this->soldPizzas()
            ->select('pizzas.pizza_type_id')
            ->selectRaw('sum(order_items.quantity) as quantity')
            ->selectRaw('sum(order_items.quantity * pizzas.price) as revenue')
            ->groupBy('pizzas.pizza_type_id');

        return DB::table('pizza_types')
            ->leftJoinSub(
                $salesByPizzaType,
                'pizza_type_sales',
                'pizza_type_sales.pizza_type_id',
                '=',
                'pizza_types.id',
            )
            ->select('pizza_types.code', 'pizza_types.name', 'pizza_types.category')
            ->selectRaw('coalesce(pizza_type_sales.quantity, 0) as quantity')
            ->selectRaw('coalesce(pizza_type_sales.revenue, 0) as revenue')
            // Revenue decides; two types with the same revenue are ordered by name, so the list never shuffles.
            ->orderByDesc('revenue')
            ->orderBy('pizza_types.name')
            ->get()
            ->map(
                fn (object $pizzaType): array => [
                    'code' => $pizzaType->code,
                    'name' => $pizzaType->name,
                    'category' => $pizzaType->category,
                    'quantity' => (int) $pizzaType->quantity,
                    'revenue' => $pizzaType->revenue,
                ],
            )
            ->all();
    }

    /**
     * @return list<array{category: string, quantity: int, revenue: string}>
     */
    public function categorySales(): array
    {
        return $this->soldPizzas()
            ->join('pizza_types', 'pizza_types.id', '=', 'pizzas.pizza_type_id')
            ->select('pizza_types.category')
            ->selectRaw('sum(order_items.quantity) as quantity')
            ->selectRaw('sum(order_items.quantity * pizzas.price) as revenue')
            ->groupBy('pizza_types.category')
            ->orderByDesc('revenue')
            ->get()
            ->map(
                fn (object $category): array => [
                    'category' => $category->category,
                    'quantity' => (int) $category->quantity,
                    'revenue' => $category->revenue,
                ],
            )
            ->all();
    }

    /**
     * Returns the order lines in the range, joined to their order and their pizza.
     */
    private function soldPizzas(): Builder
    {
        return $this->inRange(
            DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('pizzas', 'pizzas.id', '=', 'order_items.pizza_id'),
        );
    }

    /**
     * Returns the orders in the range.
     */
    private function placedOrders(): Builder
    {
        return $this->inRange(DB::table('orders'));
    }

    private function inRange(Builder $query): Builder
    {
        return $query
            ->when(
                $this->orderedFrom,
                fn (Builder $query, string $date) => $query->where('orders.ordered_at', '>=', $date),
            )
            ->when(
                $this->orderedTo,
                fn (Builder $query, string $date) => $query->where(
                    'orders.ordered_at',
                    '<',
                    CarbonImmutable::parse($date)->addDay()->toDateString(),
                ),
            );
    }
}
