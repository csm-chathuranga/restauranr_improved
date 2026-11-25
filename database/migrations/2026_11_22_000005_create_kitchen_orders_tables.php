<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->string('kot_number', 20)->index();
            $table->enum('station', ['kitchen', 'bar', 'dessert', 'cold'])->default('kitchen');
            $table->enum('status', ['new', 'preparing', 'ready', 'served'])->default('new');
            $table->boolean('priority')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['sale_id', 'station']);
        });

        Schema::create('kitchen_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kitchen_order_id');
            $table->foreign('kitchen_order_id')->references('id')->on('kitchen_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->nullOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->decimal('quantity', 10, 3);
            $table->text('modifier_summary')->nullable();
            $table->text('special_instructions')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        // Add parent_sale_id for table merge
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_sale_id')->nullable()->after('notes');
            $table->foreign('parent_sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['parent_sale_id']);
            $table->dropColumn('parent_sale_id');
        });
        Schema::dropIfExists('kitchen_order_items');
        Schema::dropIfExists('kitchen_orders');
    }
};
