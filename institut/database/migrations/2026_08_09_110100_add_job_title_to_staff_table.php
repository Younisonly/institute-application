<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('job_title_id')->nullable()->after('name');
        });

        // Backfill: map old role values to seeded job titles.
        $mapping = ['teacher' => 'معلم', 'admin_staff' => 'موظف إداري'];
        foreach (DB::table('staff')->get() as $staff) {
            $name = $mapping[$staff->role] ?? 'معلم';
            $titleId = DB::table('job_titles')->where('name', $name)->value('id')
                ?? DB::table('job_titles')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('staff')->where('id', $staff->id)->update(['job_title_id' => $titleId]);
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->foreign('job_title_id')->references('id')->on('job_titles')->nullOnDelete();
            $table->dropColumn('role');
            $table->index('job_title_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['job_title_id']);
            $table->dropIndex(['job_title_id']);
            $table->dropColumn('job_title_id');
            $table->string('role')->default('teacher')->after('name');
        });
    }
};
