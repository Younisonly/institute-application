<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\Course;
use App\Models\InstituteSetting;
use App\Models\Registration;
use App\Models\Student;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class StudentIdCardsReport extends Page implements HasForms
{

    public function getTitle(): string
    {
        return __('general.student_id_cards_report');
    }

    use HasRbac, InteractsWithForms;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.reports.id-cards';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.id_cards');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('course_id')->native(false)
                    ->label(__('general.course'))
                    ->options(fn (): array => Course::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('student_id')->native(false)
                    ->label(__('general.select_student'))
                    ->options(fn (): array => Student::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Select::make('registration_id')->native(false)
                    ->label(__('general.registration'))
                    ->options(fn (): array => Registration::query()
                        ->when($this->data['student_id'] ?? null, fn ($q) => $q->where('student_id', $this->data['student_id']))
                        ->with(['course'])
                        ->get()
                        ->mapWithKeys(fn (Registration $r): array => [
                            $r->id => $r->course?->name.' — '.$r->start_month,
                        ])
                        ->all())
                    ->searchable()
                    ->visible(fn (): bool => ! empty($this->data['student_id'])),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('filter')
                ->label(__('general.apply'))
                ->submit('applyFilters'),
        ];
    }

    public function applyFilters(): void
    {
        $this->form->getState();
    }

    public function getSelectedRegistration(): ?Registration
    {
        if (empty($this->data['registration_id'])) {
            return null;
        }

        return Registration::query()
            ->with(['student', 'course', 'batch.periods'])
            ->find((int) $this->data['registration_id']);
    }

    public function getCourseRegistrations(): Collection
    {
        if (empty($this->data['course_id'])) {
            return new Collection();
        }

        return Registration::query()
            ->where('course_id', (int) $this->data['course_id'])
            ->whereIn('status', ['active', 'suspended'])
            ->with(['student', 'course', 'batch.periods'])
            ->orderBy('student_id')
            ->get();
    }

    public function getSettings(): InstituteSetting
    {
        return InstituteSetting::current();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_course')
                ->label(__('general.print_course_cards'))
                ->icon('heroicon-o-printer')
                ->url(fn (): ?string => empty($this->data['course_id'])
                    ? null
                    : route('id-cards.course.print', ['course' => (int) $this->data['course_id']]))
                ->openUrlInNewTab()
                ->disabled(fn (): bool => empty($this->data['course_id'])),
            Action::make('print')
                ->label(__('general.print_card'))
                ->icon('heroicon-o-printer')
                ->url(fn (): ?string => empty($this->data['registration_id'])
                    ? null
                    : route('id-cards.print', ['registration' => $this->data['registration_id']]))
                ->openUrlInNewTab()
                ->disabled(fn (): bool => empty($this->data['registration_id'])),
        ];
    }
}
