<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Ingredient extends Model
{
    /**
     * @return BelongsToMany<PizzaType, $this>
     */
    public function pizzaTypes(): BelongsToMany
    {
        return $this->belongsToMany(PizzaType::class);
    }
}
