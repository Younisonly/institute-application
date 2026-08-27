<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['father_name', 'national_id']);

            $table->string('gender')->nullable()->after('name'); // male|female
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('guardian_name')->nullable()->after('address');
            $table->string('guardian_relation')->nullable()->after('guardian_name'); // father|mother|brother|sister|relative|other
            $table->string('guardian_phone')->nullable()->after('guardian_relation');
            $table->string('education_level')->nullable()->after('guardian_phone'); // basic|secondary|diploma|university|other
            $table->index('guardian_phone');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['guardian_phone']);
            $table->dropColumn(['gender', 'birth_date', 'guardian_name', 'guardian_relation', 'guardian_phone', 'education_level']);
            $table->string('father_name')->nullable();
            $table->string('national_id')->nullable();
        });
    }
};
