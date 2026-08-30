<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('courses', 'start_time')) {
                $columnsToDrop[] = 'start_time';
            }
            if (Schema::hasColumn('courses', 'end_time')) {
                $columnsToDrop[] = 'end_time';
            }
            if (Schema::hasColumn('courses', 'effective_from')) {
                $columnsToDrop[] = 'effective_from';
            }
            if (Schema::hasColumn('courses', 'effective_to')) {
                $columnsToDrop[] = 'effective_to';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('course_batches', 'start_time')) {
                $columnsToDrop[] = 'start_time';
            }
            if (Schema::hasColumn('course_batches', 'end_time')) {
                $columnsToDrop[] = 'end_time';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
        });
    }
};
