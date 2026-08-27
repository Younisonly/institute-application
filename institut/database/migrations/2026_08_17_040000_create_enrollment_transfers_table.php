<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_registration_id')->constrained('registrations')->restrictOnDelete();
            $table->foreignId('to_registration_id')->constrained('registrations')->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('to_course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('from_batch_id')->nullable()->constrained('course_batches')->nullOnDelete();
            $table->foreignId('to_batch_id')->nullable()->constrained('course_batches')->nullOnDelete();
            $table->string('reason');
            $table->decimal('balance_carried', 10, 2)->default(0);
            $table->unsignedSmallInteger('months_carried')->default(0);
            $table->boolean('carry_items')->default(false);
            $table->timestamp('transferred_at');
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'transferred_at']);
            $table->index('from_registration_id');
            $table->index('to_registration_id');
        });

        $legacy = DB::table('registrations as old')
            ->join('registrations as new', 'new.id', '=', 'old.transferred_to_id')
            ->where('old.status', 'transferred')
            ->whereNotNull('old.transferred_to_id')
            ->select(
                'old.id as from_id',
                'new.id as to_id',
                'old.student_id',
                'old.course_id as from_course_id',
                'new.course_id as to_course_id',
                'old.course_batch_id as from_batch_id',
                'new.course_batch_id as to_batch_id',
                'old.close_reason',
                'old.closed_at',
                'new.price_snapshot',
                'new.months_count',
            )
            ->get();

        if ($legacy->isNotEmpty()) {
            DB::table('enrollment_transfers')->insert(
                $legacy->map(fn ($row): array => [
                    'from_registration_id' => $row->from_id,
                    'to_registration_id' => $row->to_id,
                    'student_id' => $row->student_id,
                    'from_course_id' => $row->from_course_id,
                    'to_course_id' => $row->to_course_id,
                    'from_batch_id' => $row->from_batch_id,
                    'to_batch_id' => $row->to_batch_id,
                    'reason' => $row->close_reason ?: __('general.transfer_reason_default'),
                    'balance_carried' => $row->price_snapshot,
                    'months_carried' => $row->months_count,
                    'carry_items' => false,
                    'transferred_at' => $row->closed_at ?: now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_transfers');
    }
};
