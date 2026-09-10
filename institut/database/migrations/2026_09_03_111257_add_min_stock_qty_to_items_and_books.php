<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('min_stock_qty', 10, 2)->nullable()->default(null)->after('stock_qty');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->decimal('min_stock_qty', 10, 2)->nullable()->default(null)->after('stock_qty');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('min_stock_qty');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('min_stock_qty');
        });
    }
};
