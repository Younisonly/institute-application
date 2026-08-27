<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        Schema::create('course_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'period_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
            $table->index('period_id');
        });

        if (Schema::hasColumn('courses', 'section')) {
            $now = now();

            $morning = DB::table('periods')->insertGetId([
                'name_ar' => 'صباحي',
                'name_en' => 'Morning',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'days' => json_encode(['sat', 'sun', 'mon', 'tue', 'wed', 'thu']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $evening = DB::table('periods')->insertGetId([
                'name_ar' => 'مسائي',
                'name_en' => 'Evening',
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'days' => json_encode(['sat', 'sun', 'mon', 'tue', 'wed', 'thu']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('courses')->select('id', 'section')->orderBy('id')->chunkById(100, function ($courses) use ($morning, $evening, $now) {
                $rows = [];

                foreach ($courses as $course) {
                    $periodId = $course->section === 'evening' ? $evening : $morning;
                    $rows[] = [
                        'course_id' => $course->id,
                        'period_id' => $periodId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('course_period')->insertOrIgnore($rows);
            });

            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('section');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('courses', 'section')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('section')->nullable()->after('program_type_id');
            });
        }

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['period_id']);
            $table->dropColumn('period_id');
        });

        Schema::dropIfExists('course_period');
        Schema::dropIfExists('periods');
    }
};
