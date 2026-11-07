<?php

namespace App\Models;

use App\Enums\PizzaSize;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'pizza_type_id', 'size', 'price'])]
class Pizza extends Model
{
    /**
     * @return BelongsTo<PizzaType, $this>
     */
    public function pizzaType(): BelongsTo
    {
        return $this->belongsTo(PizzaType::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => PizzaSize::class,
            'price' => 'decimal:2',
        ];
    }
}
