<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'pizza' => new PizzaResource($this->whenLoaded('pizza')),
            'line_total' => $this->whenLoaded(
                'pizza',
                fn (): string => bcmul($this->pizza->price, (string) $this->quantity, 2),
            ),
        ];
    }
}
