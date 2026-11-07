<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pizza_types', function (Blueprint $table) {
            $table->id();
            // The source's own identifier, such as bbq_ckn, which pizzas.csv refers to.
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 20)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pizza_types');
    }
};
