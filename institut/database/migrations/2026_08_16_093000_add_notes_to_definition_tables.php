<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('days');
        });

        Schema::table('program_types', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('months_count');
        });

        Schema::table('job_titles', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('program_types', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};