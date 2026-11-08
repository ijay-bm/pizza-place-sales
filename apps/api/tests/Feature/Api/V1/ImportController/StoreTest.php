<?php

namespace Tests\Feature\Api\V1\ImportController;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PizzaType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Support\ApiTestCase;

/**
 * The fixtures under tests/Fixtures/imports/valid are cut from the dataset: orders 1, 2, 3 and 17, their ten lines,
 * and the ten pizza types and thirty pizzas those lines name, with pizza_types.csv kept in Windows-1252.
 */
class StoreTest extends ApiTestCase
{
    private const string URI = '/api/v1/imports';

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->fromTheSpa()->postJson(self::URI, $this->files())->assertUnauthorized();
    }

    public function test_the_four_files_are_imported_and_counted(): void
    {
        $this->signIn()
            ->postJson(self::URI, $this->files())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'pizza_types' => 10,
                    'ingredients' => 35,
                    'pizzas' => 30,
                    'orders' => 4,
                    'order_items' => 10,
                ],
            ]);

        $this->assertDatabaseCount('pizza_types', 10);
        $this->assertDatabaseCount('ingredients', 35);
        $this->assertDatabaseCount('pizzas', 30);
        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('order_items', 10);
    }

    public function test_an_order_keeps_its_source_id_and_joins_its_date_and_time(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();

        $this->assertSame('2015-01-01 13:53:00', Order::query()->findOrFail(17)->ordered_at->format('Y-m-d H:i:s'));
    }

    public function test_an_order_line_keeps_its_source_id_pizza_and_quantity(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();

        $orderItem = OrderItem::query()->with('pizza')->findOrFail(49);
        $this->assertSame(17, $orderItem->order_id);
        $this->assertSame('mediterraneo_m', $orderItem->pizza->code);
        $this->assertSame(2, $orderItem->quantity);
    }

    public function test_a_windows_1252_ingredient_is_stored_as_utf_8(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();

        $calabrese = PizzaType::query()->where('code', 'calabrese')->firstOrFail();
        $this->assertContains("\u{2018}Nduja Salami", $calabrese->ingredients()->pluck('name')->all());
    }

    public function test_a_quoted_name_keeps_its_commas(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();

        $this->assertSame(
            'The Pepperoni, Mushroom, and Peppers Pizza',
            PizzaType::query()->where('code', 'pep_msh_pep')->value('name'),
        );
    }

    public function test_importing_the_same_files_again_leaves_the_same_rows(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();
        $this->postJson(self::URI, $this->files())->assertOk();

        $this->assertDatabaseCount('pizza_types', 10);
        $this->assertDatabaseCount('ingredients', 35);
        $this->assertDatabaseCount(
            'ingredient_pizza_type',
            PizzaType::query()->withCount('ingredients')->get()->sum('ingredients_count'),
        );
        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('order_items', 10);
    }

    public function test_a_later_import_replaces_the_data_already_there(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();
        $this->postJson(
            self::URI,
            [
                'orders' => $this->fixture('fewer-orders', 'orders'),
                'order_details' => $this->fixture('fewer-orders', 'order_details'),
            ] + $this->files(),
        )->assertOk();

        $this->assertSame([1, 2], Order::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6], OrderItem::query()->orderBy('id')->pluck('id')->all());
    }

    public function test_a_refused_import_keeps_the_data_already_there(): void
    {
        $this->signIn()->postJson(self::URI, $this->files())->assertOk();
        $this->postJson(
            self::URI,
            ['order_details' => $this->fixture('unknown-pizza', 'order_details')] + $this->files(),
        )->assertUnprocessable();

        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('order_items', 10);
    }

    public function test_missing_files_are_refused_by_field(): void
    {
        $this->signIn()
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['pizza_types', 'pizzas', 'orders', 'order_details']);
    }

    public function test_a_file_that_is_not_csv_is_refused(): void
    {
        $this->signIn()
            ->postJson(self::URI, ['orders' => UploadedFile::fake()->create('orders.xlsx', 10)] + $this->files())
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['orders']);
    }

    public function test_an_order_line_naming_an_unknown_pizza_is_refused_and_nothing_is_written(): void
    {
        $this->signIn()
            ->postJson(
                self::URI,
                ['order_details' => $this->fixture('unknown-pizza', 'order_details')] + $this->files(),
            )
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_details.0', 'Line 3: the pizza bbq_ckn_l is not in pizzas.csv.');

        $this->assertDatabaseCount('pizza_types', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, Ingredient::query()->count());
    }

    public function test_a_file_missing_a_column_is_refused_by_name(): void
    {
        $this->signIn()
            ->postJson(self::URI, ['orders' => $this->fixture('missing-column', 'orders')] + $this->files())
            ->assertUnprocessable()
            ->assertJsonPath('errors.orders.0', 'Line 1: the columns must be order_id, date, time.');
    }

    public function test_every_file_with_the_wrong_header_is_named_at_once(): void
    {
        $this->signIn()
            ->postJson(self::URI, [
                'pizza_types' => $this->fixture('valid', 'pizzas'),
                'pizzas' => $this->fixture('valid', 'pizza_types'),
                'orders' => $this->fixture('valid', 'order_details'),
                'order_details' => $this->fixture('valid', 'orders'),
            ])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['pizza_types', 'pizzas', 'orders', 'order_details'])
            ->assertJsonPath('errors.pizzas.0', 'Line 1: the columns must be pizza_id, pizza_type_id, size, price.');

        $this->assertDatabaseCount('pizza_types', 0);
    }

    private function signIn(): static
    {
        return $this->actingAs(User::factory()->create(), 'web')->fromTheSpa();
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function files(): array
    {
        return [
            'pizza_types' => $this->fixture('valid', 'pizza_types'),
            'pizzas' => $this->fixture('valid', 'pizzas'),
            'orders' => $this->fixture('valid', 'orders'),
            'order_details' => $this->fixture('valid', 'order_details'),
        ];
    }

    private function fixture(string $case, string $file): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/Fixtures/imports/{$case}/{$file}.csv"),
            "{$file}.csv",
            'text/csv',
            null,
            true,
        );
    }
}
