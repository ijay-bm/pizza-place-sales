<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PizzaCategory;
use App\Enums\PizzaSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.ordered_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.ordered_to' => ['sometimes', 'date_format:Y-m-d'],
            'filter.category' => ['sometimes', Rule::enum(PizzaCategory::class)],
            'filter.size' => ['sometimes', Rule::enum(PizzaSize::class)],
            'filter.pizza_type' => ['sometimes', 'string', 'max:50'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            // OrderQuery's allowedIncludes() refuses any other value with a 400, so the list is not repeated here.
            'include' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
