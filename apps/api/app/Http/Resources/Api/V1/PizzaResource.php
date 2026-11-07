<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Pizza;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pizza
 */
class PizzaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'size' => $this->size,
            'price' => $this->price,
            'pizza_type' => new PizzaTypeResource($this->whenLoaded('pizzaType')),
        ];
    }
}
