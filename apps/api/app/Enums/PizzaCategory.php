<?php

namespace App\Enums;

enum PizzaCategory: string
{
    case Chicken = 'chicken';
    case Classic = 'classic';
    case Supreme = 'supreme';
    case Veggie = 'veggie';
}
