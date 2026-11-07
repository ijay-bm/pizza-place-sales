<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ShowOrderRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // OrderQuery's allowedIncludes() refuses any other value with a 400, so the list is not repeated here.
            'include' => ['sometimes', 'string'],
        ];
    }
}
