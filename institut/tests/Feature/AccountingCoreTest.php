<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\OtherPeopleTransaction;
use App\Models\OtherPerson;
use App\Models\Staff;
use App\Models\StaffTransaction;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\ReportService;
use App\Services\JournalService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Automated proof for the 2026-08-15 accounting transformation:
 * statement/ledger/trial consistency, journal-derived profit & cash flow,
 * DB-level invariants, and the chart-of-accounts surfaces.
 */
class AccountingCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    private function cashAccount(): Account
    {
        return Account::query()->where('code', '1100')->firstOrFail();
    }

    private function feesAccount(): Account
    {
        return Account::query()->where('code', '4100')->firstOrFail();
    }

    private function createStudentPayment(float $amount): void
    {
        /** @var Student $student */
        $student = Student::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first() ?? Student::query()->create(['name' => 'Accounting Test Student']);

        StudentTransaction::query()->create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => $amount,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'description' => 'Tuition payment',
        ]);
    }

    public function test_profit_is_journal_derived_and_matches_income_statement(): void
    {
        $this->createStudentPayment(10000);

        $report = app(ReportService::class)->profit(now()->format('Y-m'));

        $this->assertSame(10000.0, (float) $report['income']);
        $this->assertSame(0.0, (float) $report['spent']);
        $this->assertSame(10000.0, (float) $report['net']);

        $fees = $report['rows']->firstWhere('type', 'income');
        $this->assertSame($this->feesAccount()->id, $fees['account']->id);
        $this->assertSame(10000.0, (float) $fees['amount']);
    }

    public function test_daily_cash_collected_matches_place_debits_and_refund_sign(): void
    {
        $student = Student::query()->create(['name' => 'Daily Cash Student', 'status' => 'active']);
        StudentTransaction::query()->create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => 5000,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
        ]);
        StudentTransaction::query()->create([
            'student_id' => $student->id,
            'type' => 'refund',
            'amount' => 500,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
        ]);

        $report = app(ReportService::class)->dailyCash(now()->format('Y-m-d'));

        $this->assertSame(5000.0, (float) $report['collected']);
        $this->assertSame(500.0, (float) $report['refunded']);
        $this->assertSame(4500.0, (float) $report['net']);
    }

    public function test_supplier_payment_is_not_profit_spent_but_is_cash_out(): void
    {
        $supplier = \App\Models\Supplier::query()->first() ?? \App\Models\Supplier::query()->create(['name' => 'Test Supplier']);

        \App\Models\SupplierTransaction::query()->create([
            'supplier_id' => $supplier->id,
            'type' => 'payment',
            'amount' => 2000,
            'date' => now()->format('Y-m-d'),
            'method' => 'cash',
            'receipt_no' => 1,
        ]);

        $profit = app(ReportService::class)->profit(now()->format('Y-m'));
        $cash = app(ReportService::class)->dailyCash(now()->format('Y-m-d'));

        $this->assertSame(0.0, (float) $profit['spent']);
        $this->assertSame(2000.0, (float) $cash['spent']);
    }

    public function test_account_statement_running_balance_and_counterparty(): void
    {
        $this->createStudentPayment(3000);
        $this->createStudentPayment(4000);

        $statement = app(ReportService::class)->accountStatement($this->cashAccount());

        $this->assertSame(0.0, (float) $statement['opening']);
        $this->assertSame(7000.0, (float) $statement['closing']);
        $this->assertSame(7000.0, (float) $statement['totalDebit']);
        $this->assertCount(2, $statement['rows']);

        $first = $statement['rows'][0];
        $this->assertSame(3000.0, (float) $first['debit']);
        $this->assertSame(3000.0, (float) $first['balance']);
        $this->assertStringContainsString($this->feesAccount()->name, $statement['counterparties'][$first['line_id']] ?? '');
    }

    public function test_ledger_agrees_with_statement_and_trial_balance_is_balanced(): void
    {
        $this->createStudentPayment(9000);

        $statement = app(ReportService::class)->accountStatement($this->cashAccount());
        $ledger = app(ReportService::class)->accountLedger($this->cashAccount());
        $trial = app(ReportService::class)->trialBalance();

        $this->assertSame((float) $statement['closing'], (float) $ledger['total']);
        $this->assertSame((float) $statement['closing'], (float) $trial['totalDebit']);
        $this->assertSame((float) $trial['totalDebit'], (float) $trial['totalCredit']);
    }

    public function test_db_rejects_unnamed_account_type(): void
    {
        $this->expectException(QueryException::class);

        DB::table('accounts')->insert([
            'code' => '9999',
            'name_ar' => 'Bad Type',
            'type' => 'assetzz',
        ]);
    }

    public function test_db_rejects_negative_journal_line(): void
    {
        $this->expectException(QueryException::class);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $this->someEntryId(),
            'account_id' => $this->cashAccount()->id,
            'debit' => -10,
            'credit' => 0,
        ]);
    }

    public function test_db_rejects_self_transfer(): void
    {
        $this->expectException(QueryException::class);

        DB::table('transfers')->insert([
            'from_account_id' => $this->cashAccount()->id,
            'to_account_id' => $this->cashAccount()->id,
            'amount' => 100,
            'date' => now()->format('Y-m-d'),
            'created_by' => $this->adminUser()->id,
        ]);
    }

    public function test_chart_of_accounts_resource_renders(): void
    {
        $this->actingAs($this->adminUser())
            ->get(\App\Filament\Resources\AccountResource::getUrl('index'))
            ->assertOk();
    }

    public function test_account_statement_print_and_page_render(): void
    {
        $this->createStudentPayment(3000);

        $this->actingAs($this->adminUser())
            ->get(\App\Filament\Pages\Reports\AccountStatement::getUrl(['account_id' => $this->cashAccount()->id, 'to' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('3,000');

        $this->actingAs($this->adminUser())
            ->get(route('reports.account-statement.print', ['account_id' => $this->cashAccount()->id, 'to' => now()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('3,000');
    }

    public function test_chart_contains_party_control_accounts(): void
    {
        foreach (['1410', '1420', '1430', '2110'] as $code) {
            $this->assertNotNull(Account::query()->where('code', $code)->first(), "Missing control account {$code}");
        }
    }

    public function test_party_statement_student_sign_running_balance_and_render(): void
    {
        $student = Student::query()->create(['name' => 'Party Student', 'status' => 'active']);

        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'charge', 'amount' => 10000, 'date' => '2026-01-05', 'description' => 'Registration']);
        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'payment', 'amount' => 4000, 'date' => '2026-01-10', 'method' => 'cash']);
        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'refund', 'amount' => 2000, 'date' => '2026-01-12', 'method' => 'cash']);

        $report = app(ReportService::class)->partyLedger('student', $student->id,
            \Illuminate\Support\Carbon::parse('2026-01-01'), \Illuminate\Support\Carbon::parse('2026-01-31'));

        $this->assertSame(10000.0, (float) $report['rows'][0]['debit']);
        $this->assertSame(10000.0, (float) $report['rows'][0]['balance']);
        $this->assertSame(4000.0, (float) $report['rows'][1]['credit']);
        $this->assertSame(6000.0, (float) $report['rows'][1]['balance']);
        $this->assertSame(2000.0, (float) $report['rows'][2]['debit']);
        $this->assertSame(8000.0, (float) $report['rows'][2]['balance']);
        $this->assertSame(0.0, (float) $report['opening']);
        $this->assertSame(8000.0, (float) $report['closing']);
        $this->assertSame(12000.0, (float) $report['totalDebit']);
        $this->assertSame(4000.0, (float) $report['totalCredit']);

        $this->actingAs($this->adminUser())
            ->get(\App\Filament\Pages\Reports\AccountStatement::getUrl(['party_type' => 'student', 'party_id' => $student->id, 'to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('Party Student')
            ->assertSee('8,000');

        $this->actingAs($this->adminUser())
            ->get(route('reports.account-statement.print', ['party_type' => 'student', 'party_id' => $student->id, 'to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('Party Student')
            ->assertSee('8,000');
    }

    public function test_party_statement_opening_balance_carries_from_before_window(): void
    {
        $student = Student::query()->create(['name' => 'Window Student', 'status' => 'active']);

        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'charge', 'amount' => 5000, 'date' => '2026-01-03']);
        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'payment', 'amount' => 2000, 'date' => '2026-01-15', 'method' => 'cash']);

        $report = app(ReportService::class)->partyLedger('student', $student->id,
            \Illuminate\Support\Carbon::parse('2026-01-10'), \Illuminate\Support\Carbon::parse('2026-01-31'));

        $this->assertSame(5000.0, (float) $report['opening']);
        $this->assertSame(1, $report['rows']->count());
        $this->assertSame(2000.0, (float) $report['rows'][0]['credit']);
        $this->assertSame(3000.0, (float) $report['closing']);
    }

    public function test_party_statement_staff_advances_sign_and_salary_excluded(): void
    {
        $staff = Staff::query()->create(['name' => 'Party Staff', 'salary_type' => 'monthly', 'salary_value' => 45000, 'status' => 'active']);

        StaffTransaction::query()->create(['staff_id' => $staff->id, 'type' => 'advance', 'amount' => 5000, 'date' => '2026-02-01']);
        StaffTransaction::query()->create(['staff_id' => $staff->id, 'type' => 'repayment', 'amount' => 2000, 'date' => '2026-02-10']);
        StaffTransaction::query()->create(['staff_id' => $staff->id, 'type' => 'salary', 'amount' => 45000, 'date' => '2026-02-28']);

        // Default Advances mode: pure salary cash disbursement is excluded from advances ledger
        $report = app(ReportService::class)->partyLedger('staff', $staff->id,
            \Illuminate\Support\Carbon::parse('2026-02-01'), \Illuminate\Support\Carbon::parse('2026-02-28'));

        $this->assertSame(2, $report['rows']->count());
        $this->assertSame(5000.0, (float) $report['rows'][0]['debit']);
        $this->assertSame(2000.0, (float) $report['rows'][1]['credit']);
        $this->assertSame(3000.0, (float) $report['closing']);

        // Comprehensive mode: includes salary entitlements and payouts
        $compReport = app(ReportService::class)->partyLedger('staff', $staff->id,
            \Illuminate\Support\Carbon::parse('2026-02-01'), \Illuminate\Support\Carbon::parse('2026-02-28'), 'comprehensive');

        $this->assertSame(4, $compReport['rows']->count());
        $this->assertSame(3000.0, (float) $compReport['closing']);
    }

    public function test_party_statement_supplier_merges_purchases_and_payments(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Party Supplier']);

        StockMovement::query()->create(['supplier_id' => $supplier->id, 'type' => 'in', 'qty' => 5, 'unit_price' => 1000, 'date' => '2026-03-01', 'reference' => 'PO-1', 'description' => 'Purchase']);
        SupplierTransaction::query()->create(['supplier_id' => $supplier->id, 'type' => 'payment', 'amount' => 2000, 'date' => '2026-03-05', 'method' => 'cash']);

        $report = app(ReportService::class)->partyLedger('supplier', $supplier->id,
            \Illuminate\Support\Carbon::parse('2026-03-01'), \Illuminate\Support\Carbon::parse('2026-03-31'));

        $this->assertSame(2, $report['rows']->count());
        $this->assertSame(0.0, (float) $report['rows'][0]['debit']);
        $this->assertSame(5000.0, (float) $report['rows'][0]['credit']);
        $this->assertSame(5000.0, (float) $report['rows'][0]['balance']);
        $this->assertSame(2000.0, (float) $report['rows'][1]['debit']);
        $this->assertSame(3000.0, (float) $report['closing']);
    }

    public function test_control_account_balances_match_subsidiary_ledger(): void
    {
        $student = Student::query()->create(['name' => 'Control Student', 'status' => 'active']);
        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'charge', 'amount' => 12000, 'date' => '2026-04-01']);
        StudentTransaction::query()->create(['student_id' => $student->id, 'type' => 'payment', 'amount' => 5000, 'date' => '2026-04-02', 'method' => 'cash']);

        $other = OtherPerson::query()->create(['name' => 'Control Person']);
        OtherPeopleTransaction::query()->create(['other_person_id' => $other->id, 'type' => 'out', 'amount' => 3000, 'date' => '2026-04-03', 'method' => 'cash']);
        OtherPeopleTransaction::query()->create(['other_person_id' => $other->id, 'type' => 'in', 'amount' => 1000, 'date' => '2026-04-04', 'method' => 'cash']);

        $service = app(ReportService::class);

        $this->assertSame(7000.0, $service->controlAccountBalance(Account::query()->where('code', '1410')->firstOrFail()));
        $this->assertSame(2000.0, $service->controlAccountBalance(Account::query()->where('code', '1430')->firstOrFail()));
        // Journal-posted control accounts keep the journal balance (no override)
        $this->assertNull($service->controlAccountBalance(Account::query()->where('code', '1420')->firstOrFail()));
        $this->assertNull($service->controlAccountBalance(Account::query()->where('code', '2110')->firstOrFail()));
    }

    public function test_view_account_page_renders_with_boolean_icon_entries(): void
    {
        // Regression: TextEntry::boolean() does not exist in this Filament
        // version — boolean display must use IconEntry (crashed opening any
        // account view page).
        $this->actingAs($this->adminUser())
            ->get(\App\Filament\Resources\AccountResource::getUrl('view', ['record' => $this->cashAccount()->id]))
            ->assertOk()
            ->assertSee('1100');
    }

    public function test_party_type_switch_clears_party_id_via_set(): void
    {
        // Regression: the afterStateUpdated cascade used
        // getContainer()->getComponent('party_id')->state(null) which returned
        // null for the hidden select and crashed the livewire update.
        $this->actingAs($this->adminUser());

        Livewire::test(\App\Filament\Pages\Reports\AccountStatement::class)
            ->set('data.party_type', 'student')
            ->assertOk();
    }

    private function someEntryId(): int
    {
        return (int) app(JournalService::class)->post(
            [
                ['account_id' => $this->cashAccount()->id, 'debit' => 10, 'credit' => 0],
                ['account_id' => $this->feesAccount()->id, 'debit' => 0, 'credit' => 10],
            ],
            now()->format('Y-m-d'),
            'DB invariant probe',
        )->id;
    }
}