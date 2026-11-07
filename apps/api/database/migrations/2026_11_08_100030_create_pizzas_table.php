<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pizzas', function (Blueprint $table) {
            $table->id();
            // The source's own identifier, such as bbq_ckn_s, which order_details.csv refers to.
            $table->string('code', 50)->unique();
            $table->foreignId('pizza_type_id')->constrained();
            $table->string('size', 3);
            $table->decimal('price', 8, 2);
            $table->timestamps();
            $table->unique(['pizza_type_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pizzas');
    }
};
