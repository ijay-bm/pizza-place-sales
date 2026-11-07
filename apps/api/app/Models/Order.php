<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'ordered_at'])]
class Order extends Model
{
    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Adds pizza_count, the sum of the order's quantities, and total, the sum of each quantity times its pizza's price.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withTotals(Builder $query): void
    {
        $query->addSelect([
            'pizza_count' => OrderItem::query()
                ->selectRaw('sum(order_items.quantity)')
                ->whereColumn('order_items.order_id', 'orders.id'),
            'total' => OrderItem::query()
                ->join('pizzas', 'pizzas.id', '=', 'order_items.pizza_id')
                ->selectRaw('sum(order_items.quantity * pizzas.price)')
                ->whereColumn('order_items.order_id', 'orders.id'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'pizza_count' => 'integer',
            'total' => 'decimal:2',
        ];
    }
}
