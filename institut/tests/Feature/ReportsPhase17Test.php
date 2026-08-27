<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\ArrearsReport;
use App\Filament\Pages\Reports\EnrollmentReport;
use App\Filament\Pages\Reports\StockInventoryReport;
use App\Filament\Pages\Reports\StudentPaymentHistoryReport;
use App\Filament\Resources\JournalResource\Pages\ListJournalEntries;
use App\Models\Book;
use App\Models\Course;
use App\Models\InstituteSetting;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\ReceiptNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsPhase17Test extends TestCase
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

    private function makeCourse(string $name = 'English L1'): Course
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);

        return Course::create([
            'name' => $name,
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'is_active' => true,
        ]);
    }

    private function makeStudentWithRegistration(Course $course, string $name = 'Ali'): Registration
    {
        $student = Student::create(['name' => $name, 'status' => 'active', 'student_code' => 'S-'.uniqid(), 'guardian_phone' => '777000111']);

        return Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'price_snapshot' => 35000,
            'start_month' => '2026-06',
            'months_count' => 6,
            'status' => 'active',
            'created_by' => $this->admin()->id,
        ]);
    }

    private function makePayment(Registration $registration, float $amount, string $date): StudentTransaction
    {
        return StudentTransaction::create([
            'student_id' => $registration->student_id,
            'registration_id' => $registration->id,
            'type' => 'payment',
            'amount' => $amount,
            'date' => $date,
            'method' => 'cash',
            'receipt_no' => app(ReceiptNumberService::class)->next(),
            'created_by' => $this->admin()->id,
        ]);
    }

    public function test_payment_history_page_renders_and_filters(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeStudentWithRegistration($course);
        $this->makePayment($registration, 10000, '2026-06-15');
        $this->makePayment($registration, 5000, '2026-07-20');

        $this->actingAs($this->admin());

        $report = app(\App\Services\ReportService::class)->paymentHistory('2026-06-01', '2026-06-30', null, null);
        $this->assertCount(1, $report);
        $this->assertEquals(10000, (float) $report->first()->amount);

        Livewire::test(StudentPaymentHistoryReport::class)
            ->assertOk()
            ->set('data.from', '2026-06-01')
            ->set('data.to', '2026-06-30')
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSee('10,000');
    }

    public function test_payment_history_print_and_excel_routes(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeStudentWithRegistration($course);
        $this->makePayment($registration, 10000, '2026-06-15');

        $this->actingAs($this->admin())
            ->get('/reports/payment-history/print?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertSee('Ali');

        $this->actingAs($this->admin())
            ->get('/reports/payment-history/export?from=2026-06-01&to=2026-06-30')
            ->assertOk();
    }

    public function test_stock_inventory_merges_items_and_books(): void
    {
        $category = ItemCategory::create(['name' => 'Stationery']);
        $course = $this->makeCourse();

        Item::create([
            'name' => 'Pen',
            'category_id' => $category->id,
            'stock_qty' => 20,
            'low_stock_threshold' => 5,
            'purchase_price' => 100,
            'sale_price' => 150,
            'is_active' => true,
        ]);

        Book::create([
            'title' => 'Grammar Book',
            'course_id' => $course->id,
            'stock_qty' => 3,
            'low_stock_threshold' => 4,
            'buy_price' => 2000,
            'sale_price' => 3000,
            'is_active' => true,
        ]);

        $rows = app(\App\Services\ReportService::class)->inventory('all', null, false);
        $created = $rows->whereIn('name', ['Pen', 'Grammar Book']);
        $this->assertCount(2, $created);
        $this->assertSame(2, $created->where('type', 'item')->count() + $created->where('type', 'book')->count());
        $this->assertEquals(2000, (float) $created->where('name', 'Pen')->first()->buy_value);
        $this->assertTrue($created->where('name', 'Grammar Book')->first()->low_stock);

        $lowOnly = app(\App\Services\ReportService::class)->inventory('all', null, true);
        $this->assertTrue($lowOnly->contains(fn ($row): bool => $row->name === 'Grammar Book'));

        $itemsOnly = app(\App\Services\ReportService::class)->inventory('items', $category->id, false);
        $this->assertSame(1, $itemsOnly->count());

        $this->actingAs($this->admin());
        Livewire::test(StockInventoryReport::class)
            ->assertOk()
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSee('Pen')
            ->assertSee('Grammar Book');

        $this->actingAs($this->admin())
            ->get('/reports/stock-inventory/print')
            ->assertOk()
            ->assertSee('Pen')
            ->assertSee('Grammar Book');
    }

    public function test_enrollment_report_counts_and_filters(): void
    {
        $course = $this->makeCourse();
        $this->makeStudentWithRegistration($course, 'Ali');
        $this->makeStudentWithRegistration($course, 'Sara');
        $registration = $this->makeStudentWithRegistration($course, 'Huda');
        $registration->update(['status' => 'closed']);

        $this->actingAs($this->admin());

        $report = app(\App\Services\ReportService::class)->enrollment('2026-06', null, null, null);
        $this->assertSame(3, $report['total']);
        $this->assertSame(2, $report['active']);
        $this->assertSame(1, $report['closed']);

        Livewire::test(EnrollmentReport::class)
            ->assertOk()
            ->set('data.month', '2026-06')
            ->call('applyFilters')
            ->assertHasNoErrors();

        $this->actingAs($this->admin())
            ->get('/reports/enrollment/print?month=2026-06')
            ->assertOk()
            ->assertSee('Ali')
            ->assertSee('Huda');
    }

    public function test_arrears_record_payment_row_action(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeStudentWithRegistration($course);
        $student = $registration->student;

        StudentTransaction::create([
            'student_id' => $student->id,
            'registration_id' => $registration->id,
            'type' => 'charge',
            'amount' => 10000,
            'date' => '2026-06-01',
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ArrearsReport::class)
            ->assertOk()
            ->callTableAction('recordPayment', $student->id, [
                'registration_id' => $registration->id,
                'amount' => 4000,
                'date' => '2026-08-13',
                'method' => 'cash',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(6000, (float) Student::query()->withBalance()->findOrFail($student->id)->balance);
        $this->assertEquals(1, $student->transactions()->where('type', 'payment')->whereNull('voided_at')->count());
        $this->assertNotNull($student->transactions()->where('type', 'payment')->first()->receipt_no);
    }

    public function test_journal_resource_has_date_range_filter(): void
    {
        $course = $this->makeCourse();
        $registration = $this->makeStudentWithRegistration($course);
        $this->makePayment($registration, 10000, '2026-06-15');

        $this->actingAs($this->admin());

        Livewire::test(ListJournalEntries::class)
            ->assertOk()
            ->filterTable('date_range', ['from' => '2026-06-01', 'to' => '2026-06-30'])
            ->assertTableColumnExists('entry_no')
            ->assertCanSeeTableRecords(\App\Models\JournalEntry::query()->whereDate('date', '2026-06-15')->get());

        Livewire::test(ListJournalEntries::class)
            ->filterTable('date_range', ['from' => '2026-01-01', 'to' => '2026-01-31'])
            ->assertCanNotSeeTableRecords(\App\Models\JournalEntry::query()->get());
    }

    public function test_bulk_id_cards_print_by_course(): void
    {
        $course = $this->makeCourse();
        $registration1 = $this->makeStudentWithRegistration($course, 'Ali');
        $registration2 = $this->makeStudentWithRegistration($course, 'Sara');

        $this->actingAs($this->admin());

        $this->get("/id-cards/course/{$course->id}/print")
            ->assertOk()
            ->assertSee('Ali')
            ->assertSee('Sara');

        Livewire::test(\App\Filament\Pages\Reports\StudentIdCardsReport::class)
            ->assertOk()
            ->set('data.course_id', $course->id)
            ->call('applyFilters')
            ->assertHasNoErrors();

        $page = new \App\Filament\Pages\Reports\StudentIdCardsReport;
        $page->data['course_id'] = $course->id;
        $this->assertCount(2, $page->getCourseRegistrations());
    }
}
