<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PizzaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PizzaType
 */
class PizzaTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'pizzas' => PizzaResource::collection($this->whenLoaded('pizzas')),
            'ingredients' => $this->whenLoaded('ingredients', fn () => $this->ingredients->pluck('name')),
        ];
    }
}
