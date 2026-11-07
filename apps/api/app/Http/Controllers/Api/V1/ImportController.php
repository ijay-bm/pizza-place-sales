<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreImportRequest;
use App\Imports\InvalidSalesDataException;
use App\Imports\SalesDataImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    /**
     * Imports the four uploaded files and answers with how many rows each table received. A file whose header is
     * wrong, or a row that cannot be read, is a 422 on that file, naming the line, and nothing is written.
     */
    public function store(StoreImportRequest $request, SalesDataImporter $salesDataImporter): JsonResponse
    {
        try {
            $importedRowCounts = $salesDataImporter->import(
                pizzaTypesPath: $request->file('pizza_types')->getRealPath(),
                pizzasPath: $request->file('pizzas')->getRealPath(),
                ordersPath: $request->file('orders')->getRealPath(),
                orderDetailsPath: $request->file('order_details')->getRealPath(),
            );
        } catch (InvalidSalesDataException $exception) {
            throw ValidationException::withMessages($exception->messagesByField);
        }

        return response()->json(['data' => $importedRowCounts]);
    }
}
