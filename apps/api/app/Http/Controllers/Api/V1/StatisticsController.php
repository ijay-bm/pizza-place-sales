<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatisticsRequest;
use App\Queries\SalesStatistics;
use Illuminate\Http\JsonResponse;

/**
 * Each statistic over the orders between filter[ordered_from] and filter[ordered_to], both optional.
 */
class StatisticsController extends Controller
{
    /**
     * Returns the revenue, the orders and the pizzas sold, with the average order value and pizzas per order.
     */
    public function summary(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->summary()]);
    }

    /**
     * Returns the revenue and the orders of each month, oldest first.
     */
    public function revenueByMonth(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->revenueByMonth()]);
    }

    /**
     * Returns the orders placed in each hour of the day.
     */
    public function ordersByHour(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->ordersByHour()]);
    }

    /**
     * Returns the orders placed on each day of the week, 1 for Monday to 7 for Sunday.
     */
    public function ordersByWeekday(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->ordersByWeekday()]);
    }

    /**
     * Returns every pizza type with the pizzas it sold and its revenue, the best-selling first.
     */
    public function pizzaTypeSales(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->pizzaTypeSales()]);
    }

    /**
     * Returns each category with the pizzas it sold and its revenue, the best-selling first.
     */
    public function categorySales(StatisticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->salesStatistics($request)->categorySales()]);
    }

    private function salesStatistics(StatisticsRequest $request): SalesStatistics
    {
        return new SalesStatistics(
            orderedFrom: $request->validated('filter.ordered_from'),
            orderedTo: $request->validated('filter.ordered_to'),
        );
    }
}
