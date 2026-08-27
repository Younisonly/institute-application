<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The enrollment window and run dates live on the BATCH (the enrollable
     * unit — each batch carries its own schedule), never on the course
     * template. Courses stay enrollable purely via is_active.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'enrollment_start',
                'enrollment_end',
                'start_date',
                'end_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('enrollment_start', 7)->nullable();
            $table->string('enrollment_end', 7)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->index('enrollment_start');
            $table->index('enrollment_end');
            $table->index('start_date');
            $table->index('end_date');
        });
    }
};