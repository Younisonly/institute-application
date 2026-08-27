<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->change('student_transactions', 'student_id', 'students');
        $this->change('registrations', 'student_id', 'students');
        $this->change('staff_transactions', 'staff_id', 'staff');
        $this->change('supplier_transactions', 'supplier_id', 'suppliers');
        $this->change('other_people_transactions', 'other_person_id', 'other_people');
    }

    public function down(): void
    {
        $this->change('student_transactions', 'student_id', 'students', false);
        $this->change('registrations', 'student_id', 'students', false);
        $this->change('staff_transactions', 'staff_id', 'staff', false);
        $this->change('supplier_transactions', 'supplier_id', 'suppliers', false);
        $this->change('other_people_transactions', 'other_person_id', 'other_people', false);
    }

    private function change(string $table, string $column, string $referenced, bool $restrict = true): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $referenced, $restrict) {
            $blueprint->dropForeign([$column]);

            if ($restrict) {
                $blueprint->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
            } else {
                $blueprint->foreign($column)->references('id')->on($referenced)->cascadeOnDelete();
            }
        });
    }
};
