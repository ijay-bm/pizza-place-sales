<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexOrderRequest;
use App\Http\Requests\Api\V1\ShowOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Queries\OrderQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Returns a page of orders with their totals, filtered, searched, sorted and included by the query string.
     */
    public function index(IndexOrderRequest $request, OrderQuery $orderQuery): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $orderQuery->index()->paginate($request->integer('per_page', 25))->withQueryString(),
        );
    }

    /**
     * Returns one order with its totals, and its lines when the query string includes them.
     */
    public function show(ShowOrderRequest $request, OrderQuery $orderQuery, int $orderId): OrderResource
    {
        return new OrderResource($orderQuery->show($orderId));
    }
}
