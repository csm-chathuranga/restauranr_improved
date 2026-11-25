<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('order_type', ['dine_in', 'takeaway', 'pickup', 'delivery'])
                  ->default('dine_in')
                  ->after('table_number');

            $table->unsignedBigInteger('waiter_id')->nullable()->after('order_type');
            $table->foreign('waiter_id')->references('id')->on('users')->nullOnDelete();

            $table->tinyInteger('guest_count')->unsigned()->nullable()->after('waiter_id');
            $table->string('sequence_number', 20)->nullable()->after('guest_count');

            $table->decimal('service_charge', 10, 2)->default(0)->after('tax');

            // Delivery fields
            $table->datetime('pickup_time')->nullable()->after('sequence_number');
            $table->string('delivery_phone', 30)->nullable()->after('pickup_time');
            $table->text('delivery_address')->nullable()->after('delivery_phone');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_address');
            $table->enum('delivery_status', ['pending', 'assigned', 'on_the_way', 'delivered'])
                  ->default('pending')
                  ->nullable()
                  ->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['waiter_id']);
            $table->dropColumn([
                'order_type', 'waiter_id', 'guest_count', 'sequence_number',
                'service_charge', 'pickup_time', 'delivery_phone',
                'delivery_address', 'delivery_fee', 'delivery_status',
            ]);
        });
    }
};
