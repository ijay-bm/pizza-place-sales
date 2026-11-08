<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StatisticsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Any other filter key is refused rather than ignored.
            'filter' => ['sometimes', 'array:ordered_from,ordered_to'],
            'filter.ordered_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.ordered_to' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
