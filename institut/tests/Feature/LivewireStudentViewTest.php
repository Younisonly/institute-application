<?php

namespace Tests\Feature;

use App\Filament\Resources\StudentResource\Pages\ViewStudent;
use App\Filament\Widgets\RecommendationsWidget;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireStudentViewTest extends TestCase
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

    public function test_student_view_page_and_recommendations_widget_render_without_lazy_errors(): void
    {
        $this->actingAs($this->admin());

        $student = Student::create([
            'name' => 'Test Student Details',
            'status' => 'active',
        ]);

        $response = $this->get('/admin/students/' . $student->id);
        $response->assertOk();

        // Assert that the page content contains the recommendations widget heading directly (non-lazy)
        $this->assertStringContainsString(__('general.recommendations'), $response->getContent());

        Livewire::test(ViewStudent::class, ['record' => $student->id])
            ->call('$refresh')
            ->assertStatus(200);

        Livewire::test(RecommendationsWidget::class, ['record' => $student])
            ->call('$refresh')
            ->assertStatus(200);
    }
}
