<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('original_price', 16, 2)->nullable()->after('price_snapshot');
            $table->decimal('discount_amount', 16, 2)->default(0)->after('original_price');
            $table->string('discount_type', 50)->nullable()->after('discount_amount');
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $table->json('fee_schedule')->nullable()->after('is_active');
        });

        DB::table('registrations')
            ->whereNull('original_price')
            ->update(['original_price' => DB::raw('price_snapshot')]);
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn('fee_schedule');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount', 'discount_type']);
        });
    }
};
