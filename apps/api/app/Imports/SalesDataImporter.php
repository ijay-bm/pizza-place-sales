<?php

namespace App\Imports;

use App\Enums\PizzaCategory;
use App\Enums\PizzaSize;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pizza;
use App\Models\PizzaType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Imports the four files of Kaggle's pizza place sales dataset.
 */
class SalesDataImporter
{
    private const int CHUNK_SIZE = 1000;

    /**
     * Imports the four files in one transaction, in the order their references run, and returns how many rows each
     * table received. Every file's header is checked first. Rows are matched on the source's identifiers, so
     * importing the same files again changes nothing. A row that cannot be read stops the import and nothing is
     * written.
     *
     * @return array{pizza_types: int, ingredients: int, pizzas: int, orders: int, order_items: int}
     *
     * @throws InvalidSalesDataException
     */
    public function import(
        string $pizzaTypesPath,
        string $pizzasPath,
        string $ordersPath,
        string $orderDetailsPath,
    ): array {
        $pizzaTypesFile = new CsvFile($pizzaTypesPath, 'pizza_types', [
            'pizza_type_id',
            'name',
            'category',
            'ingredients',
        ]);
        $pizzasFile = new CsvFile($pizzasPath, 'pizzas', ['pizza_id', 'pizza_type_id', 'size', 'price']);
        $ordersFile = new CsvFile($ordersPath, 'orders', ['order_id', 'date', 'time']);
        $orderDetailsFile = new CsvFile($orderDetailsPath, 'order_details', [
            'order_details_id',
            'order_id',
            'pizza_id',
            'quantity',
        ]);

        $this->checkHeaders($pizzaTypesFile, $pizzasFile, $ordersFile, $orderDetailsFile);

        return DB::transaction(function () use ($pizzaTypesFile, $pizzasFile, $ordersFile, $orderDetailsFile): array {
            $pizzaTypeIdByCode = $this->importPizzaTypes($pizzaTypesFile);
            $ingredientIdByName = $this->importIngredients($pizzaTypesFile, $pizzaTypeIdByCode);
            $pizzaIdByCode = $this->importPizzas($pizzasFile, $pizzaTypeIdByCode);
            $orderIds = $this->importOrders($ordersFile);
            $orderItemCount = $this->importOrderItems($orderDetailsFile, $orderIds, $pizzaIdByCode);

            return [
                'pizza_types' => count($pizzaTypeIdByCode),
                'ingredients' => count($ingredientIdByName),
                'pizzas' => count($pizzaIdByCode),
                'orders' => count($orderIds),
                'order_items' => $orderItemCount,
            ];
        });
    }

    /**
     * Checks every file's header before anything is imported, so a request with several wrong files names each one.
     *
     * @throws InvalidSalesDataException
     */
    private function checkHeaders(CsvFile ...$csvFiles): void
    {
        $messagesByField = [];

        foreach ($csvFiles as $csvFile) {
            $headerError = $csvFile->headerError();

            if ($headerError !== null) {
                $messagesByField[$csvFile->name] = $headerError;
            }
        }

        if ($messagesByField !== []) {
            throw new InvalidSalesDataException($messagesByField);
        }
    }

    /**
     * @return array<string, int> the pizza type ids by code
     */
    private function importPizzaTypes(CsvFile $pizzaTypesFile): array
    {
        $pizzaTypes = [];

        foreach ($pizzaTypesFile->rows() as $line => $row) {
            $category =
                PizzaCategory::tryFrom(strtolower($row['category'])) ??
                throw $pizzaTypesFile->invalid(
                    $line,
                    "the category {$row['category']} is not one of " . $this->categoryNames() . '.',
                );

            $pizzaTypes[] = ['code' => $row['pizza_type_id'], 'name' => $row['name'], 'category' => $category->value];
        }

        PizzaType::query()->upsert($pizzaTypes, ['code'], ['name', 'category']);

        return PizzaType::query()->whereIn('code', array_column($pizzaTypes, 'code'))->pluck('id', 'code')->all();
    }

    /**
     * Imports the ingredients each pizza type lists in its ingredients field, and links each type to its own.
     *
     * @param  array<string, int>  $pizzaTypeIdByCode
     * @return array<string, int> the ingredient ids by name
     */
    private function importIngredients(CsvFile $pizzaTypesFile, array $pizzaTypeIdByCode): array
    {
        $ingredientNamesByCode = [];

        foreach ($pizzaTypesFile->rows() as $row) {
            $ingredientNamesByCode[$row['pizza_type_id']] = array_map(trim(...), explode(',', $row['ingredients']));
        }

        $ingredientNames = collect($ingredientNamesByCode)->flatten()->unique()->values()->all();

        // upsert() needs a column to update; writing the name over itself makes it insert only the names that are new.
        Ingredient::query()->upsert(
            array_map(fn (string $name): array => ['name' => $name], $ingredientNames),
            ['name'],
            ['name'],
        );
        $ingredientIdByName = Ingredient::query()->whereIn('name', $ingredientNames)->pluck('id', 'name')->all();

        foreach (PizzaType::query()->whereIn('id', $pizzaTypeIdByCode)->get() as $pizzaType) {
            $ingredientIds = array_map(
                fn (string $name): int => $ingredientIdByName[$name],
                $ingredientNamesByCode[$pizzaType->code],
            );
            $pizzaType->ingredients()->sync($ingredientIds);
        }

        return $ingredientIdByName;
    }

    /**
     * @param  array<string, int>  $pizzaTypeIdByCode
     * @return array<string, int> the pizza ids by code
     */
    private function importPizzas(CsvFile $pizzasFile, array $pizzaTypeIdByCode): array
    {
        $pizzas = [];

        foreach ($pizzasFile->rows() as $line => $row) {
            $pizzaTypeId =
                $pizzaTypeIdByCode[$row['pizza_type_id']] ??
                throw $pizzasFile->invalid($line, "the pizza type {$row['pizza_type_id']} is not in pizza_types.csv.");
            $size =
                PizzaSize::tryFrom($row['size']) ??
                throw $pizzasFile->invalid(
                    $line,
                    "the size {$row['size']} is not one of " .
                        implode(', ', array_column(PizzaSize::cases(), 'value')) .
                        '.',
                );

            if (! is_numeric($row['price']) || (float) $row['price'] < 0) {
                throw $pizzasFile->invalid($line, "the price {$row['price']} is not an amount.");
            }

            $pizzas[] = [
                'code' => $row['pizza_id'],
                'pizza_type_id' => $pizzaTypeId,
                'size' => $size->value,
                'price' => $row['price'],
            ];
        }

        Pizza::query()->upsert($pizzas, ['code'], ['pizza_type_id', 'size', 'price']);

        return Pizza::query()->whereIn('code', array_column($pizzas, 'code'))->pluck('id', 'code')->all();
    }

    /**
     * Imports the orders, joining each one's date and time into ordered_at.
     *
     * @return array<int, true> the imported order ids, as keys
     */
    private function importOrders(CsvFile $ordersFile): array
    {
        $orders = [];

        foreach ($ordersFile->rows() as $line => $row) {
            if (! ctype_digit($row['order_id'])) {
                throw $ordersFile->invalid($line, "the order id {$row['order_id']} is not a whole number.");
            }

            $dateAndTime = "{$row['date']} {$row['time']}";
            $orderedAt = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $dateAndTime);

            // createFromFormat rolls an impossible date over into the next month, so a round trip catches it.
            if ($orderedAt === null || $orderedAt->format('Y-m-d H:i:s') !== $dateAndTime) {
                throw $ordersFile->invalid($line, "{$dateAndTime} is not a date and time.");
            }

            $orders[] = ['id' => (int) $row['order_id'], 'ordered_at' => $orderedAt->format('Y-m-d H:i:s')];
        }

        foreach (array_chunk($orders, self::CHUNK_SIZE) as $chunk) {
            Order::query()->upsert($chunk, ['id'], ['ordered_at']);
        }

        return array_fill_keys(array_column($orders, 'id'), true);
    }

    /**
     * Imports the order lines.
     *
     * @param  array<int, true>  $orderIds
     * @param  array<string, int>  $pizzaIdByCode
     */
    private function importOrderItems(CsvFile $orderDetailsFile, array $orderIds, array $pizzaIdByCode): int
    {
        $orderItems = [];

        foreach ($orderDetailsFile->rows() as $line => $row) {
            if (! ctype_digit($row['order_details_id'])) {
                throw $orderDetailsFile->invalid(
                    $line,
                    "the order line id {$row['order_details_id']} is not a whole number.",
                );
            }

            if (! isset($orderIds[(int) $row['order_id']])) {
                throw $orderDetailsFile->invalid($line, "the order {$row['order_id']} is not in orders.csv.");
            }

            $pizzaId =
                $pizzaIdByCode[$row['pizza_id']] ??
                throw $orderDetailsFile->invalid($line, "the pizza {$row['pizza_id']} is not in pizzas.csv.");

            if (! ctype_digit($row['quantity']) || (int) $row['quantity'] === 0) {
                throw $orderDetailsFile->invalid(
                    $line,
                    "the quantity {$row['quantity']} is not a whole number above zero.",
                );
            }

            $orderItems[] = [
                'id' => (int) $row['order_details_id'],
                'order_id' => (int) $row['order_id'],
                'pizza_id' => $pizzaId,
                'quantity' => (int) $row['quantity'],
            ];
        }

        foreach (array_chunk($orderItems, self::CHUNK_SIZE) as $chunk) {
            OrderItem::query()->upsert($chunk, ['id'], ['order_id', 'pizza_id', 'quantity']);
        }

        return count($orderItems);
    }

    /**
     * Returns the categories as pizza_types.csv writes them, capitalized: Chicken, Classic, Supreme, Veggie.
     */
    private function categoryNames(): string
    {
        return implode(
            ', ',
            array_map(fn (PizzaCategory $category): string => ucfirst($category->value), PizzaCategory::cases()),
        );
    }
}
