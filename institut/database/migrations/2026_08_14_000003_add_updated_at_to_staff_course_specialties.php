<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_course_specialties', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        DB::table('staff_course_specialties')
            ->whereNull('updated_at')
            ->update(['updated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('staff_course_specialties', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }
};
