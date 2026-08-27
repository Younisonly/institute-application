<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_transactions')) {
            Schema::table('staff_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('staff_transactions', 'rate_snapshot')) {
                    $table->decimal('rate_snapshot', 10, 2)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('staff_transactions', 'hours_snapshot')) {
                    $table->decimal('hours_snapshot', 10, 2)->nullable()->after('rate_snapshot');
                }
                if (! Schema::hasColumn('staff_transactions', 'percentage_snapshot')) {
                    $table->decimal('percentage_snapshot', 5, 2)->nullable()->after('hours_snapshot');
                }
                if (! Schema::hasColumn('staff_transactions', 'salary_type_snapshot')) {
                    $table->string('salary_type_snapshot', 30)->nullable()->after('percentage_snapshot');
                }
            });
        }

        if (Schema::hasTable('institute_settings')) {
            Schema::table('institute_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('institute_settings', 'financial_lock_date')) {
                    $table->date('financial_lock_date')->nullable()->after('certificate_next_no');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_transactions')) {
            Schema::table('staff_transactions', function (Blueprint $table) {
                $table->dropColumn(['rate_snapshot', 'hours_snapshot', 'percentage_snapshot', 'salary_type_snapshot']);
            });
        }

        if (Schema::hasTable('institute_settings')) {
            Schema::table('institute_settings', function (Blueprint $table) {
                $table->dropColumn('financial_lock_date');
            });
        }
    }
};
