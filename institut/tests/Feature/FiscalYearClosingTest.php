<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Course;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYearClosing;
use App\Models\InstituteSetting;
use App\Models\JournalEntry;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountService;
use App\Services\FiscalYearClosingService;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalYearClosingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@institute.local')->firstOrFail();
    }

    private function setCurrentMonth(string $month): void
    {
        InstituteSetting::query()->firstOrFail()->update(['current_month' => $month]);
    }

    private function makeCourse(): Course
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        return Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'is_active' => true,
        ]);
    }

    private function record2026Activity(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-15',
        ], $this->admin()->id);

        $category = ExpenseCategory::create(['name' => 'Utilities']);
        Expense::create([
            'expense_category_id' => $category->id,
            'amount' => 4000,
            'date' => '2026-07-01',
            'payment_method' => 'cash',
            'description' => 'Electricity bill',
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_closing_zeroes_income_expense_and_moves_net_to_retained_earnings(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();

        $service = app(FiscalYearClosingService::class);
        $preview = $service->preview(2026);
        $this->assertEquals(10000, $preview['totalIncome']);
        $this->assertEquals(4000, $preview['totalExpenses']);
        $this->assertEquals(6000, $preview['net']);

        $closing = $service->close(2026, $this->admin()->id);

        $entry = $closing->journalEntry;
        $this->assertNotNull($entry);
        $this->assertSame('2026-12-31', $entry->date->toDateString());
        $this->assertSame('yearly-closing-2026', $entry->reference);
        $this->assertEqualsWithDelta($entry->debit_total, $entry->credit_total, 0.001);
        $this->assertSame(FiscalYearClosing::class, $entry->document_type);

        $fees = Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail();
        $expense = Account::query()->where('code', AccountService::CODE_EXPENSE_OTHER)->firstOrFail();
        $retained = Account::query()->where('code', AccountService::CODE_RETAINED_EARNINGS)->firstOrFail();

        $this->assertEquals(0, (float) $fees->balance(), 'income accounts must be zeroed after closing');
        $this->assertEquals(0, (float) $expense->balance(), 'expense accounts must be zeroed after closing');
        $this->assertEquals(6000, (float) $retained->balance(), 'net result must land in retained earnings');

        $this->assertDatabaseHas('fiscal_year_closings', ['year' => 2026]);
    }

    public function test_income_statement_and_balance_sheet_stay_correct_after_closing(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();

        $service = app(FiscalYearClosingService::class);
        $before = app(ReportService::class)->incomeStatement(
            \Illuminate\Support\Carbon::createFromDate(2026, 1, 1),
            \Illuminate\Support\Carbon::createFromDate(2026, 12, 31),
        );
        $service->close(2026, $this->admin()->id);

        $after = app(ReportService::class)->incomeStatement(
            \Illuminate\Support\Carbon::createFromDate(2026, 1, 1),
            \Illuminate\Support\Carbon::createFromDate(2026, 12, 31),
        );
        $this->assertEquals($before['totalIncome'], $after['totalIncome'], 'closing entry must not alter the year income statement');
        $this->assertEquals($before['totalExpenses'], $after['totalExpenses']);
        $this->assertEquals(6000, $after['net']);

        $balanceSheet = app(ReportService::class)->balanceSheet();
        $this->assertEquals(0, $balanceSheet['netIncome'], 'net income line must not double count a closed year');
        $this->assertEquals($balanceSheet['totalAssets'], $balanceSheet['totalLiabilities']);
    }

    public function test_closed_year_blocks_new_entries_and_voids_until_reopened(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();
        $service = app(FiscalYearClosingService::class);
        $service->close(2026, $this->admin()->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/2026/');

        app(\App\Services\JournalService::class)->post(
            lines: [
                ['account_id' => Account::query()->where('code', AccountService::CODE_CASH)->firstOrFail()->id, 'debit' => 100],
                ['account_id' => Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail()->id, 'credit' => 100],
            ],
            date: '2026-08-20',
        );
    }

    public function test_voiding_a_transaction_inside_a_closed_year_is_blocked(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();

        $payment = \App\Models\StudentTransaction::query()->where('type', 'payment')->firstOrFail();
        $service = app(FiscalYearClosingService::class);
        $service->close(2026, $this->admin()->id);

        try {
            $payment->void('mistake');
            $this->fail('voiding inside a closed year must throw');
        } catch (ValidationException) {
            $this->assertNull($payment->fresh()->voided_at);
        }
    }

    public function test_reopen_reverses_closing_entry_and_unlocks_the_year(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();
        $service = app(FiscalYearClosingService::class);
        $closing = $service->close(2026, $this->admin()->id);
        $entry = $closing->journalEntry;

        $service->reopen(2026, $this->admin()->id);

        $this->assertDatabaseMissing('fiscal_year_closings', ['year' => 2026]);
        $this->assertTrue($entry->fresh()->isVoided(), 'closing entry must be voided on reopen');
        $this->assertNotNull($entry->fresh()->reversed_entry_id);

        $fees = Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail();
        $this->assertEquals(10000, (float) $fees->balance(), 'income balance must be restored after reopen');

        app(\App\Services\JournalService::class)->post(
            lines: [
                ['account_id' => Account::query()->where('code', AccountService::CODE_CASH)->firstOrFail()->id, 'debit' => 50],
                ['account_id' => Account::query()->where('code', AccountService::CODE_INCOME_COURSE_FEES)->firstOrFail()->id, 'credit' => 50],
            ],
            date: '2026-09-01',
        );
        $this->assertTrue(true);
    }

    public function test_current_year_cannot_be_closed(): void
    {
        $this->setCurrentMonth('2026-12');
        $this->record2026Activity();

        $this->expectException(ValidationException::class);
        app(FiscalYearClosingService::class)->close(2026, $this->admin()->id);
    }

    public function test_double_close_is_blocked_and_empty_year_is_rejected(): void
    {
        $this->setCurrentMonth('2027-01');
        $this->record2026Activity();
        $service = app(FiscalYearClosingService::class);
        $service->close(2026, $this->admin()->id);

        try {
            $service->close(2026, $this->admin()->id);
            $this->fail('double closing must throw');
        } catch (ValidationException) {
            $this->assertSame(1, FiscalYearClosing::query()->where('year', 2026)->count());
        }

        $this->expectException(ValidationException::class);
        $service->close(2025, $this->admin()->id);
    }
}
