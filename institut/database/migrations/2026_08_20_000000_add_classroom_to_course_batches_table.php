<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The class (classroom/room) a batch studies in — Yemeni institutes
     * assign each دفعة a فصل/قاعة; kept as a free-text label because
     * rooms are named per-institute (فصل 1، قاعة B، ...).
     */
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('classroom', 100)->nullable()->after('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn('classroom');
        });
    }
};
