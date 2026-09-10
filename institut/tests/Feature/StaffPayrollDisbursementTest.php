<?php

namespace Tests\Feature;

use App\Filament\Resources\StaffPayrollPeriodResource;
use App\Models\JournalEntry;
use App\Models\Staff;
use App\Models\StaffPayrollPeriod;
use App\Models\StaffTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPayrollDisbursementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_payout_fails_when_no_approved_payroll_period_exists(): void
    {
        $staff = Staff::create([
            'name' => 'Test Staff',
            'salary_type' => 'monthly',
            'salary_value' => 60000,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 10000,
            'date' => now()->toDateString(),
            'salary_month' => '2026-08',
            'method' => 'cash',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_payout_succeeds_up_to_accrued_payable_amount(): void
    {
        $staff = Staff::create([
            'name' => 'Test Staff',
            'salary_type' => 'monthly',
            'salary_value' => 60000,
        ]);

        $period = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_salary' => 60000,
            'gross_salary' => 60000,
            'net_salary' => 60000,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        app(\App\Services\FinancePostingService::class)->postPayrollApproval($period);

        $tx = StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 40000,
            'date' => now()->toDateString(),
            'salary_month' => '2026-08',
            'method' => 'cash',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('staff_transactions', [
            'id' => $tx->id,
            'amount' => 40000.00,
        ]);

        $period->refresh();
        $this->assertEquals('partially_paid', $period->status);
    }

    public function test_multi_accrual_approval_in_same_month(): void
    {
        $staff = Staff::create([
            'name' => 'Test Staff',
            'salary_type' => 'monthly',
            'salary_value' => 80000,
        ]);

        $period1 = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_salary' => 80000,
            'gross_salary' => 50000,
            'net_salary' => 50000,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);
        app(\App\Services\FinancePostingService::class)->postPayrollApproval($period1);

        $period2 = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_salary' => 80000,
            'gross_salary' => 30000,
            'net_salary' => 30000,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);
        app(\App\Services\FinancePostingService::class)->postPayrollApproval($period2);

        $this->assertEquals(2, StaffPayrollPeriod::where('staff_id', $staff->id)->where('salary_month', '2026-08')->count());
        $this->assertEquals(80000.00, StaffPayrollPeriod::where('staff_id', $staff->id)->where('salary_month', '2026-08')->sum('net_salary'));
    }

    public function test_staff_payroll_period_cancellation_reverses_journal_entry(): void
    {
        $staff = Staff::create([
            'name' => 'Test Staff',
            'salary_type' => 'monthly',
            'salary_value' => 50000,
        ]);

        $period = StaffPayrollPeriod::create([
            'staff_id' => $staff->id,
            'salary_month' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_salary' => 50000,
            'gross_salary' => 50000,
            'net_salary' => 50000,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);
        app(\App\Services\FinancePostingService::class)->postPayrollApproval($period);

        $journalEntry = JournalEntry::where('document_type', StaffPayrollPeriod::class)
            ->where('document_id', $period->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertNull($journalEntry->voided_at);

        Livewire::test(StaffPayrollPeriodResource\Pages\ListStaffPayrollPeriods::class)
            ->callTableAction('cancelAccrual', $period, [
                'reason' => 'Duplicate entry correction',
            ]);

        $period->refresh();
        $this->assertEquals('cancelled', $period->status);

        $journalEntry->refresh();
        $this->assertNotNull($journalEntry->voided_at);
        $this->assertEquals('Duplicate entry correction', $journalEntry->void_reason);
    }
}
