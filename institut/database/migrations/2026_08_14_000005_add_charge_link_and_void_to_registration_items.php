<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('registration_item_id')->nullable()->after('registration_id');
            $table->foreign('registration_item_id')->references('id')->on('registration_items')->restrictOnDelete();
            $table->index('registration_item_id');
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('description');
            $table->string('void_reason')->nullable()->after('voided_at');

            $table->dropForeign(['registration_id']);
            $table->foreign('registration_id')->references('id')->on('registrations')->restrictOnDelete();
        });

        Schema::table('registration_months', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->foreign('registration_id')->references('id')->on('registrations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_months', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
        });

        Schema::table('registration_items', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();

            $table->dropColumn(['void_reason', 'voided_at']);
        });

        Schema::table('student_transactions', function (Blueprint $table) {
            $table->dropForeign(['registration_item_id']);
            $table->dropIndex(['registration_item_id']);
            $table->dropColumn('registration_item_id');
        });
    }
};