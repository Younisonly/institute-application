<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['code' => '3200'],
            [
                'name_ar' => 'الأرباح المرحلة',
                'name_en' => 'Retained earnings',
                'type' => 'equity',
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Schema::create('fiscal_year_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('net', 10, 2)->default(0);
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closings');

        DB::table('accounts')->where('code', '3200')->delete();
    }
};
