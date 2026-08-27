<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_types', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('name');
            $table->string('study_system', 20)->default('annual')->after('months_count');
            $table->string('status', 20)->default('active')->after('study_system');
        });
    }

    public function down(): void
    {
        Schema::table('program_types', function (Blueprint $table) {
            $table->dropColumn(['code', 'study_system', 'status']);
        });
    }
};
