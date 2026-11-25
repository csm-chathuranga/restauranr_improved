<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->smallInteger('pos_x')->default(0)->after('notes');
            $table->smallInteger('pos_y')->default(0)->after('pos_x');
            $table->tinyInteger('width')->unsigned()->default(2)->after('pos_y');
            $table->tinyInteger('height')->unsigned()->default(2)->after('width');
            $table->enum('shape', ['rectangle', 'circle', 'bar'])->default('rectangle')->after('height');
            $table->string('section', 100)->nullable()->after('shape');
            $table->tinyInteger('rotation')->default(0)->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y', 'width', 'height', 'shape', 'section', 'rotation']);
        });
    }
};
