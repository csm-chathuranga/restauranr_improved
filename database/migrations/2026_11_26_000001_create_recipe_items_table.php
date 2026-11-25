<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            // The dish being sold (e.g. Chicken Kottu)
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // The stock item consumed to make one serving (e.g. Flour), in the ingredient's own unit
            $table->foreignId('ingredient_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
