<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete all soft-deleted records to prevent uniqueness constraint issues when removing the column
        \Illuminate\Support\Facades\DB::table('job_titles')->whereNotNull('deleted_at')->delete();
        
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
