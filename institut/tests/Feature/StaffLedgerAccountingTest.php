<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffLedgerAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    public function test_staff_advances_statement_is_mathematically_balanced(): void
    {
        $staff = Staff::create([
            'name' => 'Mohamed Ahmed Teacher',
            'phone' => '770000000',
            'salary_type' => 'monthly',
            'salary_value' => 70000.00,
            'status' => 'active',
            'is_teacher' => true,
        ]);

        // 1. Give advance (10,000 YER)
        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'advance',
            'amount' => 10000.00,
            'date' => '2026-08-28',
            'method' => 'cash',
            'created_by' => $this->adminUser()->id,
        ]);

        // 2. Pay salary (60,000 YER cash) + Advance Deduction (10,000 YER)
        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'salary',
            'amount' => 60000.00,
            'date' => '2026-08-28',
            'salary_month' => '2026-08',
            'method' => 'cash',
            'created_by' => $this->adminUser()->id,
        ]);

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'deduction',
            'amount' => 10000.00,
            'date' => '2026-08-28',
            'salary_month' => '2026-08',
            'method' => 'cash',
            'created_by' => $this->adminUser()->id,
        ]);

        $reportService = app(ReportService::class);

        // Mode 1: Staff Advances Register (Default)
        $advancesReport = $reportService->partyLedger('staff', $staff->id, null, null, 'advances');

        $this->assertEquals(10000.0, $advancesReport['totalDebit']);
        $this->assertEquals(10000.0, $advancesReport['totalCredit']);
        $this->assertEquals(0.0, $advancesReport['closing']);
        $this->assertEquals($advancesReport['totalDebit'], $advancesReport['totalCredit']);

        // Mode 2: Comprehensive Staff Statement
        $compReport = $reportService->partyLedger('staff', $staff->id, null, null, 'comprehensive');

        $this->assertEquals(70000.0, $compReport['totalDebit']);
        $this->assertEquals(70000.0, $compReport['totalCredit']);
        $this->assertEquals(0.0, $compReport['closing']);
        $this->assertEquals($compReport['totalDebit'], $compReport['totalCredit']);
    }

    public function test_account_statement_page_renders_staff_statement_modes(): void
    {
        $this->actingAs($this->adminUser());

        $staff = Staff::create([
            'name' => 'Mohamed Ahmed Teacher 2',
            'phone' => '771111111',
            'salary_type' => 'monthly',
            'salary_value' => 70000.00,
            'status' => 'active',
            'is_teacher' => true,
        ]);

        StaffTransaction::create([
            'staff_id' => $staff->id,
            'type' => 'advance',
            'amount' => 10000.00,
            'date' => '2026-08-28',
            'method' => 'cash',
            'created_by' => auth()->id(),
        ]);

        Livewire::test(\App\Filament\Pages\Reports\AccountStatement::class)
            ->set('data.party_type', 'staff')
            ->set('data.party_id', $staff->id)
            ->set('data.staff_statement_mode', 'advances')
            ->assertSuccessful()
            ->set('data.staff_statement_mode', 'comprehensive')
            ->assertSuccessful();
    }
}
