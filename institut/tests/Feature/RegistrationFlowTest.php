<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\EnrollmentTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\RegistrationMonth;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
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

    private function makeCourse(): Course
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 3]);

        return Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 3,
            'price' => 35000,
            'is_active' => true,
        ]);
    }

    public function test_registration_creates_months_charges_and_receipt(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 3,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-08-10',
        ], $this->admin()->id);

        $months = $registration->months()->pluck('month')->all();
        $this->assertSame('active', $registration->status);
        $this->assertSame(3, count($months));
        $this->assertSame('2026-10', $registration->expected_end);

        $charged = StudentTransaction::query()->where('registration_id', $registration->id)->where('type', 'charge')->sum('amount');
        $paid = StudentTransaction::query()->where('registration_id', $registration->id)->where('type', 'payment')->sum('amount');
        $this->assertEquals(35000, (float) $charged);
        $this->assertEquals(10000, (float) $paid);

        $payment = StudentTransaction::query()->where('registration_id', $registration->id)->where('type', 'payment')->first();
        $this->assertNotNull($payment->receipt_no);

        $totals = Registration::query()->withTotals()->findOrFail($registration->id);
        $this->assertEquals(25000, $totals->balance);
    }

    public function test_add_month_extends_count_creates_prorated_charge(): void
    {
        $student = Student::create(['name' => 'Nadia', 'status' => 'active']);
        $course = $this->makeCourse();

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'payment_amount' => 0,
        ], $this->admin()->id);

        app(RegistrationService::class)->addMonth($registration, '2027-02', $this->admin()->id);

        $fresh = $registration->fresh();
        $this->assertSame(7, $fresh->months_count);
        $this->assertSame('2027-02', $fresh->months()->where('month', '2027-02')->value('month'));
        $this->assertSame(1, $fresh->months()->where('month', '2027-02')->count());

        $extension = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'charge')
            ->where('description', 'like', '%2027-02')
            ->firstOrFail();
        $this->assertEquals(5833.33, (float) $extension->amount);

        $totals = Registration::query()->withTotals()->findOrFail($registration->id);
        $this->assertEquals(40833.33, (float) $totals->balance);
    }

    public function test_add_month_rejects_duplicate_and_closed_registration(): void
    {
        $student = Student::create(['name' => 'Omar', 'status' => 'active']);
        $course = $this->makeCourse();

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'payment_amount' => 0,
        ], $this->admin()->id);

        try {
            app(RegistrationService::class)->addMonth($registration, '2026-10', $this->admin()->id);
            $this->fail('Expected ValidationException for an existing month.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('month', $e->errors());
        }

        app(RegistrationService::class)->close($registration, 'withdrew', $this->admin()->id);

        try {
            app(RegistrationService::class)->addMonth($registration->fresh(), '2027-03', $this->admin()->id);
            $this->fail('Expected ValidationException for a closed registration.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('month', $e->errors());
        }

        $this->assertSame(6, $registration->fresh()->months_count);
    }

    public function test_registration_issues_items_and_deducts_stock(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);
        $course = $this->makeCourse();
        $category = ItemCategory::create(['name' => 'Books']);
        $item = Item::create([
            'name' => 'Workbook',
            'category_id' => $category->id,
            'stock_qty' => 10,
            'low_stock_threshold' => 2,
            'sale_price' => 2000,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 2000, 'description' => null],
            ],
            'payment_amount' => 0,
            'payment_method' => 'cash',
        ], $this->admin()->id);

        $this->assertSame(8, $item->fresh()->stock_qty);

        $issue = $item->fresh()->movements()->where('type', 'issue')->first();
        $this->assertNotNull($issue);
        $this->assertSame(2, $issue->qty);

        $itemCharge = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'charge')
            ->where('description', 'Workbook × 2')
            ->first();
        $this->assertNotNull($itemCharge);
        $this->assertEquals(4000, (float) $itemCharge->amount);
    }

    public function test_registration_rejects_insufficient_stock(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $category = ItemCategory::create(['name' => 'Books']);
        $item = Item::create([
            'name' => 'Workbook',
            'category_id' => $category->id,
            'stock_qty' => 1,
            'sale_price' => 2000,
        ]);

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [
                ['item_id' => $item->id, 'qty' => 5, 'unit_price' => 2000],
            ],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $this->assertSame(1, $item->fresh()->stock_qty);
    }

    public function test_transfer_carries_balance_and_keeps_history(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $type = $course->programType;
        $other = Course::create([
            'name' => 'English L2',
            'program_type_id' => $type->id,
            
            'months' => 6,
            'price' => 35000,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 20000,
        ], $this->admin()->id);

        $new = app(RegistrationService::class)->transfer($registration, $other->id, 'upgrade', $this->admin()->id);

        $this->assertSame('transferred', $registration->fresh()->status);
        $this->assertSame($new->id, $registration->fresh()->transferred_to_id);
        $this->assertSame($student->id, $new->student_id);
        $this->assertEquals(15000, (float) $new->price_snapshot);

        $carried = StudentTransaction::query()
            ->where('registration_id', $new->id)
            ->where('type', 'transfer_debit')
            ->first();
        $this->assertNotNull($carried);
        $this->assertEquals(15000, (float) $carried->amount);

        $this->assertEquals(0.0, (float) $registration->fresh()->withTotals()->first()->balance);
        $this->assertEquals(15000.0, (float) $student->fresh()->withBalance()->first()->balance);

        $register = EnrollmentTransfer::query()
            ->where('from_registration_id', $registration->id)
            ->where('to_registration_id', $new->id)
            ->first();
        $this->assertNotNull($register);
        $this->assertSame($student->id, $register->student_id);
        $this->assertSame($course->id, $register->from_course_id);
        $this->assertSame($other->id, $register->to_course_id);
        $this->assertSame('upgrade', $register->reason);
        $this->assertEquals(15000, (float) $register->balance_carried);
        $this->assertSame(6, $register->months_carried);
        $this->assertFalse($register->carry_items);
        $this->assertNotNull($register->transferred_at);
        $this->assertSame($this->admin()->id, $register->transferred_by);
        $this->assertSame($this->admin()->id, $register->approved_by);
    }

    public function test_voiding_issued_item_reverses_charge_and_stock(): void
    {
        $student = Student::create(['name' => 'Sara', 'status' => 'active']);
        $course = $this->makeCourse();
        $category = ItemCategory::create(['name' => 'Books']);
        $item = Item::create([
            'name' => 'Workbook',
            'category_id' => $category->id,
            'stock_qty' => 5,
            'sale_price' => 2000,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [
                ['item_id' => $item->id, 'qty' => 1, 'unit_price' => 2000],
            ],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $registrationItem = $registration->items()->firstOrFail();
        app(RegistrationService::class)->voidIssuedItem($registrationItem, 'student returned the book');

        $this->assertSame(5, $item->fresh()->stock_qty);
        $this->assertNotNull($registrationItem->fresh()->voided_at);
        $this->assertSame('student returned the book', $registrationItem->fresh()->void_reason);
        $this->assertNull($registration->items()->active()->find($registrationItem->id));

        $charge = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'charge')
            ->where('description', 'Workbook × 1')
            ->firstOrFail();
        $this->assertNotNull($charge->voided_at);
        $this->assertEquals($registrationItem->id, $charge->fresh()->registration_item_id);
    }

    public function test_registration_and_receipt_pages_render(): void
    {
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $course = $this->makeCourse();
        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'price_snapshot' => 35000,
            'items' => [],
            'payment_amount' => 15000,
        ], $this->admin()->id);

        $payment = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'payment')
            ->firstOrFail();

        $this->actingAs($this->admin())->get('/admin/registrations')->assertOk();
        $this->actingAs($this->admin())->get('/admin/registrations/create')->assertOk();
        $this->actingAs($this->admin())->get("/admin/registrations/{$registration->id}")->assertOk();
        $this->actingAs($this->admin())->get(route('receipts.print', $payment))->assertOk()->assertSee((string) $payment->receipt_no);
        $this->actingAs($this->admin())->get(route('students.statement', $student))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.daily-cash.print', ['date' => '2026-08-10']))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.profit.print', ['month' => '2026-08']))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.arrears.print'))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.registrations.print'))->assertOk();
        $this->actingAs($this->admin())->get(route('id-cards.print', $registration))->assertOk();
        $this->actingAs($this->admin())->get(route('reports.salary-sheet.print', ['month' => '2026-08']))->assertOk();
        $this->actingAs($this->admin())->get('/admin/items')->assertOk();
        $this->actingAs($this->admin())->get('/admin/expenses')->assertOk();
        $this->actingAs($this->admin())->get('/admin/daily-cash-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/profit-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/arrears-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/registration-lists-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/student-id-cards-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/salary-sheet-report')->assertOk();
        $this->actingAs($this->admin())->get('/admin/program-types')->assertOk();
        $this->actingAs($this->admin())->get('/admin/courses')->assertOk();
        $this->actingAs($this->admin())->get('/admin/item-categories')->assertOk();
        $this->actingAs($this->admin())->get('/admin/suppliers')->assertOk();
        $this->actingAs($this->admin())->get('/admin/expense-categories')->assertOk();
    }

    public function test_register_with_discount_snapshots_original_and_net(): void
    {
        $student = Student::create(['name' => 'Mona', 'status' => 'active']);
        $course = $this->makeCourse();

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'original_price' => 35000,
            'discount_amount' => 5000,
            'discount_type' => 'scholarship',
            'price_snapshot' => 30000,
            'items' => [],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $this->assertEquals(30000, (float) $registration->fresh()->price_snapshot);
        $this->assertEquals(35000, (float) $registration->fresh()->original_price);
        $this->assertEquals(5000, (float) $registration->fresh()->discount_amount);
        $this->assertSame('scholarship', $registration->fresh()->discount_type);

        $charged = StudentTransaction::query()
            ->where('registration_id', $registration->id)
            ->where('type', 'charge')
            ->whereNull('voided_at')
            ->sum('amount');
        $this->assertEquals(30000, (float) $charged);

        $this->assertEquals(30000.0, (float) $student->fresh()->withBalance()->first()->balance);
    }

    public function test_discount_cannot_exceed_original_fee(): void
    {
        $student = Student::create(['name' => 'Mona', 'status' => 'active']);
        $course = $this->makeCourse();

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'original_price' => 35000,
            'discount_amount' => 40000,
            'price_snapshot' => 0,
            'items' => [],
            'payment_amount' => 0,
        ], $this->admin()->id);
    }

    public function test_net_price_must_match_original_minus_discount(): void
    {
        $student = Student::create(['name' => 'Mona', 'status' => 'active']);
        $course = $this->makeCourse();

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'original_price' => 35000,
            'discount_amount' => 5000,
            'price_snapshot' => 31000,
            'items' => [],
            'payment_amount' => 0,
        ], $this->admin()->id);
    }

    public function test_batch_fee_override_is_snapshotted_at_enrollment(): void
    {
        $student = Student::create(['name' => 'Huda', 'status' => 'active']);
        $course = $this->makeCourse();
        $batch = \App\Models\CourseBatch::create([
            'course_id' => $course->id,
            'name' => '2026-B',
            'year' => '2026',
            'fee_schedule' => ['price' => 40000],
            'is_active' => true,
        ]);

        $registration = app(RegistrationService::class)->register([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_batch_id' => $batch->id,
            'start_month' => '2026-08',
            'months_count' => 6,
            'original_price' => 40000,
            'discount_amount' => 0,
            'price_snapshot' => 40000,
            'items' => [],
            'payment_amount' => 0,
        ], $this->admin()->id);

        $this->assertEquals(40000, (float) $registration->fresh()->price_snapshot);
        $this->assertEquals(40000, (float) $registration->fresh()->original_price);

        $course->update(['price' => 50000]);
        $this->assertEquals(40000, (float) $registration->fresh()->price_snapshot);
        $this->assertEquals(40000, (float) $registration->fresh()->original_price);
    }
}
