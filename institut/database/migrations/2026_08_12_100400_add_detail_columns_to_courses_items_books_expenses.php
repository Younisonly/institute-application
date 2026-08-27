<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('unit', 30)->nullable();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('edition', 50)->nullable();
            $table->string('isbn', 20)->nullable();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['description', 'capacity']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['edition', 'isbn']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};