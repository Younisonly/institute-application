<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('hours_per_session', 5, 2)->default(2.00)->after('price');
            $table->unsignedSmallInteger('number_of_sessions')->default(15)->after('hours_per_session');
            $table->unsignedSmallInteger('total_planned_hours')->default(30)->after('number_of_sessions');
            $table->json('working_days')->nullable()->after('total_planned_hours');
            $table->time('start_time')->nullable()->after('working_days');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedSmallInteger('break_duration')->default(0)->after('end_time'); // in minutes
            $table->date('effective_from')->nullable()->after('break_duration');
            $table->date('effective_to')->nullable()->after('effective_from');
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('working_days');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedSmallInteger('break_duration')->default(0)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'break_duration']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'hours_per_session',
                'number_of_sessions',
                'total_planned_hours',
                'working_days',
                'start_time',
                'end_time',
                'break_duration',
                'effective_from',
                'effective_to',
            ]);
        });
    }
};
