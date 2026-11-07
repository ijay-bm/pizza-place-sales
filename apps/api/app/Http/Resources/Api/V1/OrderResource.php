<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // The source has no time zone, so the moment is sent as written, without an offset.
            'ordered_at' => $this->ordered_at->format('Y-m-d\TH:i:s'),
            'pizza_count' => $this->pizza_count,
            'total' => $this->total,
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
