<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE items ADD CONSTRAINT check_items_stock CHECK (stock_qty >= 0)');
        DB::statement('ALTER TABLE books ADD CONSTRAINT check_books_stock CHECK (stock_qty >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE items DROP CONSTRAINT IF EXISTS check_items_stock');
        DB::statement('ALTER TABLE books DROP CONSTRAINT IF EXISTS check_books_stock');
    }
};
