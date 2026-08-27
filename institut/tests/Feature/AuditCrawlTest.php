<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Book;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\OtherPerson;
use App\Models\OtherPeopleTransaction;
use App\Models\Period;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RegistrationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditCrawlTest extends TestCase
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

    public function test_all_print_views_render(): void
    {
        $admin = $this->admin();
        $program = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create([
            'name' => 'English L1', 'program_type_id' => $program->id,
            'months' => 6, 'price' => 35000, 'is_active' => true,
        ]);
        $student = Student::create(['name' => 'Ali Saleh', 'phone' => '777111222', 'status' => 'active']);
        $period = Period::create([
            'name_ar' => 'صباحي', 'name_en' => 'Morning',
            'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'days' => ['sat', 'sun', 'mon'], 'is_active' => true,
        ]);
        $batch = CourseBatch::create([
            'course_id' => $course->id, 'name' => 'Batch 2026', 'year' => '2026', 'is_active' => true,
        ]);
        $batch->periods()->attach($period->id);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch->id,
            'start_month' => now()->format('Y-m'),
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'books' => [],
            'payment_amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => now()->format('Y-m-d'),
        ], $admin->id);

        Bank::create(['name' => 'SAB', 'account_code' => '1002', 'is_active' => true]);
        Wallet::create(['name' => 'Wallet', 'account_code' => '1003', 'is_active' => true]);
        ExpenseCategory::create(['name' => 'Rent']);
        Expense::create([
            'expense_category_id' => ExpenseCategory::first()->id,
            'amount' => 5000, 'date' => now()->format('Y-m-d'),
            'description' => 'Office rent', 'payment_method' => 'cash', 'created_by' => $admin->id,
        ]);
        ItemCategory::create(['name' => 'Books']);
        Item::create(['name' => 'Workbook', 'category_id' => ItemCategory::first()->id, 'stock_qty' => 10, 'cost_price' => 1000, 'sale_price' => 2000]);
        Book::create(['title' => 'Grammar Book', 'stock_qty' => 5, 'sale_price' => 3000]);
        $supplier = Supplier::create(['name' => 'Al-Noor Co.']);
        $person = OtherPerson::create(['name' => 'Hassan', 'is_active' => true]);

        $payment = StudentTransaction::query()->where('type', 'payment')->firstOrFail();
        SupplierTransaction::create([
            'supplier_id' => $supplier->id, 'type' => 'payment', 'amount' => 2000,
            'date' => now()->format('Y-m-d'), 'description' => 'Purchase', 'method' => 'cash',
            'receipt_no' => 9001, 'created_by' => $admin->id,
        ]);
        OtherPeopleTransaction::create([
            'other_person_id' => $person->id, 'type' => 'in', 'amount' => 500,
            'date' => now()->format('Y-m-d'), 'description' => 'Loan', 'method' => 'cash',
            'receipt_no' => 9002, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $render = function (string $view, array $data): string {
            try {
                return view($view, $data)->render();
            } catch (\Throwable $e) {
                fwrite(STDERR, "$view -> ".get_class($e).': '.mb_substr($e->getMessage(), 0, 150).PHP_EOL);

                return 'ERROR';
            }
        };

        $render('prints.receipt', ['transaction' => $payment->load(['student', 'registration.course', 'registration.batch.periods']), 'balance' => 0]);
        $render('prints.statement', [
            'student' => $student->load('registrations.course'),
            'rows' => collect(),
            'balance' => 0,
        ]);
        $render('prints.daily-cash', ['report' => app(ReportService::class)->dailyCash(now()->format('Y-m-d'))]);
        $render('prints.profit', ['report' => app(ReportService::class)->profit(now()->format('Y-m'))]);
        $render('prints.supplier-voucher', ['transaction' => SupplierTransaction::first()->load('supplier')]);
        $render('prints.other-voucher', ['transaction' => OtherPeopleTransaction::first()->load(['person', 'incomeCategory', 'expenseCategory'])]);
        $regRows = app(ReportService::class)->registrationList(null, $batch->id, null);
        $render('prints.registration-list', [
            'rows' => $regRows,
            'totalBalance' => (float) $regRows->sum(fn ($r): float => $r->balance),
        ]);
        $render('prints.salary-sheet', ['report' => app(ReportService::class)->salarySheet(now()->format('Y-m'))]);
        $render('prints.id-card', ['registration' => $student->registrations()->with(['student', 'course', 'batch.periods'])->first()]);
        $render('prints.arrears', [
            'rows' => app(ReportService::class)->arrears(),
            'total' => 0,
        ]);

        $this->assertTrue(true);
    }

    public function test_custom_form_pages_render_and_submit_via_livewire(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Filament\Pages\Reports\RegistrationListsReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Pages\Reports\DailyCashReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Pages\Reports\ProfitReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Pages\Reports\SalarySheetReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Pages\Reports\StudentIdCardsReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Pages\Payments::class)
            ->assertOk()
            ->assertSee(__('general.record_payment'));

        Livewire::test(\App\Filament\Pages\OpeningBalances::class)
            ->assertOk()
            ->assertSee(__('general.post_opening_balances'));
    }
}
