<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PizzaTypeResource;
use App\Models\PizzaType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PizzaTypeController extends Controller
{
    /**
     * Returns every pizza type by name, with its sizes from the cheapest, which is the smallest.
     */
    public function index(): AnonymousResourceCollection
    {
        return PizzaTypeResource::collection(
            PizzaType::query()
                ->with(['pizzas' => fn (HasMany $pizzas) => $pizzas->orderBy('price')])
                ->orderBy('name')
                ->get(),
        );
    }
}
