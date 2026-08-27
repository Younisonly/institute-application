<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('year', 20)->nullable();
            $table->char('enrollment_start', 7)->nullable();
            $table->char('enrollment_end', 7)->nullable();
            $table->char('start_month', 7)->nullable();
            $table->char('end_month', 7)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'is_active']);
            $table->index('start_month');
            $table->index('year');
        });

        Schema::create('course_batch_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_batch_id', 'period_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('course_batch_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
            $table->index('course_batch_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('pass_mark')->nullable()->after('full_mark');
        });

        // Backfill: one default batch per course that already has registrations,
        // then attach every existing registration to its course's batch.
        DB::table('registrations')
            ->selectRaw('MAX(id) as id, course_id, MIN(start_month) as first_month, COUNT(*) as total')
            ->whereNotNull('course_id')
            ->groupBy('course_id')
            ->orderBy('course_id')
            ->chunkById(100, function ($groups) {
                $now = now();
                $courseIds = $groups->pluck('course_id')->all();

                $courseNames = DB::table('courses')
                    ->whereIn('id', $courseIds)
                    ->pluck('name', 'id');

                foreach ($groups as $group) {
                    $year = substr((string) $group->first_month, 0, 4);
                    $name = ($courseNames[$group->course_id] ?? '')
                        .' — '.__('general.batch_of_year', ['year' => $year]);

                    $batchId = DB::table('course_batches')->insertGetId([
                        'course_id' => $group->course_id,
                        'name' => $name,
                        'year' => $year,
                        'enrollment_start' => $group->first_month,
                        'enrollment_end' => $group->first_month,
                        'start_month' => $group->first_month,
                        'end_month' => null,
                        'capacity' => null,
                        'teacher_id' => DB::table('courses')->where('id', $group->course_id)->value('teacher_id'),
                        'notes' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('registrations')
                        ->where('course_id', $group->course_id)
                        ->whereNull('course_batch_id')
                        ->update(['course_batch_id' => $batchId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['course_batch_id']);
            $table->dropColumn('course_batch_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('pass_mark');
        });

        Schema::dropIfExists('course_batch_period');
        Schema::dropIfExists('course_batches');
    }
};