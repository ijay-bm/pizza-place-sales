<?php

namespace App\Models;

use App\Enums\PizzaCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'category'])]
class PizzaType extends Model
{
    /**
     * @return HasMany<Pizza, $this>
     */
    public function pizzas(): HasMany
    {
        return $this->hasMany(Pizza::class);
    }

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PizzaCategory::class,
        ];
    }
}
