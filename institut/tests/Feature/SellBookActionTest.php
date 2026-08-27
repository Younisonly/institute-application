<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\ViewBook;
use App\Filament\Resources\RegistrationResource\Pages\ViewRegistration;
use App\Models\Book;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\ProgramType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellBookActionTest extends TestCase
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

    private function makeRegistrationWithBook(): array
    {
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'is_active' => true,
        ]);
        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Dar Al-Kitab']);
        $book = Book::create([
            'title' => 'English Starter',
            'course_id' => $course->id,
            'supplier_id' => $supplier->id,
            'buy_price' => 1500,
            'sale_price' => 2000,
            'stock_qty' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);
        $registration = Registration::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'price_snapshot' => 35000,
            'start_month' => '2026-08',
            'months_count' => 6,
            'status' => 'active',
            'created_by' => $this->admin()->id,
        ]);

        return [$registration, $book];
    }

    public function test_sell_book_modal_opens_on_registration_without_type_error(): void
    {
        $this->actingAs($this->admin());

        [$registration, $book] = $this->makeRegistrationWithBook();

        Livewire::test(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertOk()
            ->callAction('sellBook')
            ->assertOk();
    }

    public function test_walk_in_sell_modal_opens_without_type_error(): void
    {
        $this->actingAs($this->admin());

        [$registration, $book] = $this->makeRegistrationWithBook();

        Livewire::test(ViewBook::class, ['record' => $book->getRouteKey()])
            ->assertOk()
            ->callAction('sellWalkIn')
            ->assertOk();
    }

    public function test_sell_book_with_payment_completes(): void
    {
        $this->actingAs($this->admin());

        [$registration, $book] = $this->makeRegistrationWithBook();

        Livewire::test(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertOk()
            ->callAction('sellBook', data: [
                'book_id' => $book->id,
                'qty' => 2,
                'unit_price' => 2000,
                'total' => 4000,
                'date' => '2026-08-13',
                'pay_now' => true,
                'method' => 'cash',
            ])
            ->assertHasNoActionErrors()
            ->assertOk();

        $this->assertSame(8, $book->fresh()->stock_qty);
        $this->assertDatabaseCount('student_transactions', 2);
    }

    public function test_batch_select_on_registration_lists_batches(): void
    {
        $this->actingAs($this->admin());

        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create([
            'name' => 'English L1',
            'program_type_id' => $type->id,
            'months' => 6,
            'price' => 35000,
            'is_active' => true,
        ]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => '2026-A', 'is_active' => true]);

        $component = Livewire::test(\App\Filament\Resources\RegistrationResource\Pages\CreateRegistration::class)
            ->assertOk()
            ->fillForm([
                'student_id' => Student::create(['name' => 'Sara', 'status' => 'active'])->id,
                'course_id' => $course->id,
                'price_snapshot' => 35000,
                'months_count' => 6,
                'start_month' => '2026-08',
            ])
            ->assertOk();

        $component->call('getFormSelectOptions', 'data.course_batch_id');
        $labels = collect($component->effects['returns'][0])->pluck('label', 'value')->all();
        $this->assertArrayHasKey((string) $batch->id, $labels);
        $this->assertStringContainsString('2026-A', implode(' ', $labels));
    }
}
