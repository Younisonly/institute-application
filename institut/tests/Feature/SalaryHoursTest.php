<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\SalarySheetReport;
use App\Models\JobTitle;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function perHourStaff(): Staff
    {
        $job = JobTitle::create(['name' => 'مدرس نظري']);

        return Staff::create([
            'name' => 'Hourly Teacher',
            'job_title_id' => $job->id,
            'salary_type' => 'per_hour',
            'salary_value' => 3000,
            'status' => 'active',
        ]);
    }

    public function test_record_hours_creates_salary_for_the_month(): void
    {
        $staff = $this->perHourStaff();

        $page = new SalarySheetReport();
        $page->data = ['month' => '2026-08'];
        $page->recordHoursAction()->call([
            'arguments' => ['staff_id' => $staff->id],
            'data' => ['hours' => '2.5', 'method' => 'cash'],
        ]);

        $tx = StaffTransaction::query()
            ->where('staff_id', $staff->id)
            ->where('type', 'salary')
            ->firstOrFail();
        $this->assertSame('7500.00', (string) $tx->amount);
        $this->assertSame('2026-08', $tx->salary_month);
        $this->assertSame('2026-08-31', $tx->date->format('Y-m-d'));
    }

    public function test_record_hours_marks_staff_paid_in_salary_sheet(): void
    {
        $staff = $this->perHourStaff();

        $page = new SalarySheetReport();
        $page->data = ['month' => '2026-08'];
        $page->recordHoursAction()->call([
            'arguments' => ['staff_id' => $staff->id],
            'data' => ['hours' => '4', 'method' => 'cash'],
        ]);

        $report = app(\App\Services\ReportService::class)->salarySheet('2026-08');
        $row = $report['rows']->firstWhere('staff.id', $staff->id);
        $this->assertTrue($row['paid']);
        $this->assertSame('per_hour', $row['salary_type']);
    }

    public function test_picker_state_is_normalized_to_month_format(): void
    {
        $staff = $this->perHourStaff();

        $page = new SalarySheetReport();
        $page->data = ['month' => '2026-08-25 00:00:00'];
        $this->assertSame('2026-08', $page->selectedMonth());

        $page->data = ['month' => '2026-08'];
        $this->assertSame('2026-08', $page->selectedMonth());
    }
}