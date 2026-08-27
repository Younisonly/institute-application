<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\Book;
use App\Models\Course;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationBooksReproTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_register_with_book_row_added_via_action_then_create(): void
    {
        $admin = User::query()->where('email', 'admin@institute.local')->firstOrFail();
        $this->actingAs($admin);

        $student = Student::create(['name' => 'Ali', 'status' => 'active']);
        $type = ProgramType::create(['name' => 'Short', 'months_count' => 6]);
        $course = Course::create(['name' => 'English L1', 'program_type_id' => $type->id, 'months' => 6, 'price' => 35000, 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Dar']);
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

        $component = Livewire::test(CreateRegistration::class)->assertOk();

        $component->set('data.student_id', (string) $student->id);
        $component->set('data.course_id', (string) $course->id);
        $component->set('data.start_month', '2026-08');
        $component->set('data.original_price', '35000');
        $component->set('data.discount_amount', '0');
        $component->set('data.price_snapshot', '35000');
        $component->set('data.months_count', '6');
        $component->set('data.payment_amount', '10000');
        $component->set('data.payment_method', 'cash');

        $component->callFormComponentAction('books', 'add');
        $books = $component->get('data.books');
        $firstKey = array_key_first($books);

        $component->set("data.books.{$firstKey}.book_id", (string) $book->id)->assertOk();

        $after = $component->get('data.books');
        $this->assertSame('2000.00', $after[$firstKey]['unit_price'] ?? null);

        $component->call('create');
        $component->assertHasNoErrors();

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('registration_items', 1);
        $this->assertSame(9, $book->fresh()->stock_qty);
    }
}
