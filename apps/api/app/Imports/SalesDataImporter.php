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
     * Replaces the sales data with the four files, in one transaction, and returns how many rows each table received.
     * Every file's header is checked first. The rows already stored are deleted, then the files are inserted in the
     * order their references run, each row keeping its source id. A row that cannot be read stops the import, and the
     * data already stored is left as it was.
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
            $this->deleteSalesData();

            $pizzaTypeIdByCode = $this->importPizzaTypes($pizzaTypesFile);
            $ingredientIdByName = $this->importIngredients($pizzaTypesFile);
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
     * Deletes every row of the sales data, the referencing tables first. ingredient_pizza_type is emptied by its
     * cascade from pizza_types. DELETE and not TRUNCATE, since MySQL commits the open transaction on a TRUNCATE.
     */
    private function deleteSalesData(): void
    {
        OrderItem::query()->delete();
        Order::query()->delete();
        Pizza::query()->delete();
        PizzaType::query()->delete();
        Ingredient::query()->delete();
    }

    /**
     * @return array<string, int> the pizza type ids by code
     */
    private function importPizzaTypes(CsvFile $pizzaTypesFile): array
    {
        $pizzaTypes = [];
        $lineByCode = [];

        foreach ($pizzaTypesFile->rows() as $line => $row) {
            if (isset($lineByCode[$row['pizza_type_id']])) {
                throw $pizzaTypesFile->invalid(
                    $line,
                    "the pizza type {$row['pizza_type_id']} is already on line {$lineByCode[$row['pizza_type_id']]}.",
                );
            }

            $lineByCode[$row['pizza_type_id']] = $line;

            $category =
                PizzaCategory::tryFrom(strtolower($row['category'])) ??
                throw $pizzaTypesFile->invalid(
                    $line,
                    "the category {$row['category']} is not one of " . $this->categoryNames() . '.',
                );

            $pizzaTypes[] = ['code' => $row['pizza_type_id'], 'name' => $row['name'], 'category' => $category->value];
        }

        PizzaType::query()->fillAndInsert($pizzaTypes);

        return PizzaType::query()->pluck('id', 'code')->all();
    }

    /**
     * Imports the ingredients each pizza type lists in its ingredients field, and links each type to its own.
     *
     * @return array<string, int> the ingredient ids by name
     */
    private function importIngredients(CsvFile $pizzaTypesFile): array
    {
        $ingredientNamesByCode = [];

        foreach ($pizzaTypesFile->rows() as $line => $row) {
            $ingredientNamesOfPizzaType = array_map(trim(...), explode(',', $row['ingredients']));
            $repeatedNames = array_diff_assoc($ingredientNamesOfPizzaType, array_unique($ingredientNamesOfPizzaType));

            if ($repeatedNames !== []) {
                throw $pizzaTypesFile->invalid($line, 'the ingredient ' . reset($repeatedNames) . ' is listed twice.');
            }

            $ingredientNamesByCode[$row['pizza_type_id']] = $ingredientNamesOfPizzaType;
        }

        $ingredientNames = collect($ingredientNamesByCode)->flatten()->unique()->values()->all();

        Ingredient::query()->fillAndInsert(array_map(fn (string $name): array => ['name' => $name], $ingredientNames));
        $ingredientIdByName = Ingredient::query()->pluck('id', 'name')->all();

        foreach (PizzaType::query()->get() as $pizzaType) {
            $ingredientIds = array_map(
                fn (string $name): int => $ingredientIdByName[$name],
                $ingredientNamesByCode[$pizzaType->code],
            );
            $pizzaType->ingredients()->attach($ingredientIds);
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
        $lineByCode = [];
        $lineByPizzaTypeAndSize = [];

        foreach ($pizzasFile->rows() as $line => $row) {
            if (isset($lineByCode[$row['pizza_id']])) {
                throw $pizzasFile->invalid(
                    $line,
                    "the pizza {$row['pizza_id']} is already on line {$lineByCode[$row['pizza_id']]}.",
                );
            }

            $lineByCode[$row['pizza_id']] = $line;

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

            $pizzaTypeAndSize = "{$row['pizza_type_id']} in size {$size->value}";

            if (isset($lineByPizzaTypeAndSize[$pizzaTypeAndSize])) {
                throw $pizzasFile->invalid(
                    $line,
                    "the pizza type {$pizzaTypeAndSize} is already on line {$lineByPizzaTypeAndSize[$pizzaTypeAndSize]}.",
                );
            }

            $lineByPizzaTypeAndSize[$pizzaTypeAndSize] = $line;

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

        Pizza::query()->fillAndInsert($pizzas);

        return Pizza::query()->pluck('id', 'code')->all();
    }

    /**
     * Imports the orders, joining each one's date and time into ordered_at.
     *
     * @return array<int, true> the imported order ids, as keys
     */
    private function importOrders(CsvFile $ordersFile): array
    {
        $orders = [];
        $lineByOrderId = [];

        foreach ($ordersFile->rows() as $line => $row) {
            if (! ctype_digit($row['order_id'])) {
                throw $ordersFile->invalid($line, "the order id {$row['order_id']} is not a whole number.");
            }

            $orderId = (int) $row['order_id'];

            if (isset($lineByOrderId[$orderId])) {
                throw $ordersFile->invalid(
                    $line,
                    "the order {$orderId} is already on line {$lineByOrderId[$orderId]}.",
                );
            }

            $lineByOrderId[$orderId] = $line;

            $dateAndTime = "{$row['date']} {$row['time']}";
            $orderedAt = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $dateAndTime);

            // createFromFormat rolls an impossible date over into the next month, so a round trip catches it.
            if ($orderedAt === null || $orderedAt->format('Y-m-d H:i:s') !== $dateAndTime) {
                throw $ordersFile->invalid($line, "{$dateAndTime} is not a date and time.");
            }

            $orders[] = ['id' => $orderId, 'ordered_at' => $orderedAt->format('Y-m-d H:i:s')];
        }

        foreach (array_chunk($orders, self::CHUNK_SIZE) as $chunk) {
            Order::query()->fillAndInsert($chunk);
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
        $lineByOrderItemId = [];

        foreach ($orderDetailsFile->rows() as $line => $row) {
            if (! ctype_digit($row['order_details_id'])) {
                throw $orderDetailsFile->invalid(
                    $line,
                    "the order line id {$row['order_details_id']} is not a whole number.",
                );
            }

            $orderItemId = (int) $row['order_details_id'];

            if (isset($lineByOrderItemId[$orderItemId])) {
                throw $orderDetailsFile->invalid(
                    $line,
                    "the order line {$orderItemId} is already on line {$lineByOrderItemId[$orderItemId]}.",
                );
            }

            $lineByOrderItemId[$orderItemId] = $line;

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
                'id' => $orderItemId,
                'order_id' => (int) $row['order_id'],
                'pizza_id' => $pizzaId,
                'quantity' => (int) $row['quantity'],
            ];
        }

        foreach (array_chunk($orderItems, self::CHUNK_SIZE) as $chunk) {
            OrderItem::query()->fillAndInsert($chunk);
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
