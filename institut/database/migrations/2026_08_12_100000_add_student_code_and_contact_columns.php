<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code', 20)->unique()->nullable()->after('id');
            $table->string('whatsapp_phone', 20)->nullable()->after('phone');
            $table->string('national_id', 20)->nullable()->after('whatsapp_phone');
        });

        $students = DB::table('students')->whereNull('student_code')->orderBy('id')->get();
        foreach ($students as $student) {
            DB::table('students')
                ->where('id', $student->id)
                ->update(['student_code' => 'STU-'.str_pad((string) $student->id, 5, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['student_code']);
            $table->dropColumn(['student_code', 'whatsapp_phone', 'national_id']);
        });
    }
};