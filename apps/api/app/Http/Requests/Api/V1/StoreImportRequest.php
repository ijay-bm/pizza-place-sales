<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    /**
     * Each field takes one of the dataset's four files; 10 MB is several times the largest, order_details.csv.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'pizza_types' => ['required', 'file', 'extensions:csv', 'max:10240'],
            'pizzas' => ['required', 'file', 'extensions:csv', 'max:10240'],
            'orders' => ['required', 'file', 'extensions:csv', 'max:10240'],
            'order_details' => ['required', 'file', 'extensions:csv', 'max:10240'],
        ];
    }
}
