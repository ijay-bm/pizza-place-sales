<?php

namespace App\Enums;

enum PizzaSize: string
{
    case Small = 'S';
    case Medium = 'M';
    case Large = 'L';
    case ExtraLarge = 'XL';
    case ExtraExtraLarge = 'XXL';
}
