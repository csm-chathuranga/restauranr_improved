<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('draft','completed','merged') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('draft','completed') NOT NULL DEFAULT 'draft'");
    }
};
