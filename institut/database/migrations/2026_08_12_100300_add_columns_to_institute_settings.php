<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->string('website')->nullable();
            $table->string('institute_type', 30)->nullable();
            $table->year('founded_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropColumn(['website', 'institute_type', 'founded_year']);
        });
    }
};