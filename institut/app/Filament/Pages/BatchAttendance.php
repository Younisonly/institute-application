<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseBatch;
use App\Models\Registration;
use App\Services\AttendanceService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

class BatchAttendance extends Page implements HasForms, HasTable
{
    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'registrar', 'teacher'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.batch-attendance';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public function getTitle(): string
    {
        return __('general.attendance_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.attendance');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('course_batch_id')->native(false)
                    ->label(__('general.batch'))
                    ->placeholder(__('general.select_batch'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->options(fn (): array => CourseBatch::query()
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (CourseBatch $batch): array => [
                            $batch->id => $batch->option_label.' — '.($batch->course?->name ?? ''),
                        ])
                        ->all())
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Registration::query()
                ->with(['student'])
                ->when(
                    (int) ($this->data['course_batch_id'] ?? 0) > 0,
                    fn (Builder $q) => $q->where('course_batch_id', (int) ($this->data['course_batch_id'] ?? 0)),
                    fn (Builder $q) => $q->whereKey(0),
                )
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('general.student'))
                    ->weight('semibold'),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("general.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended', 'closed' => 'danger',
                        'graduated' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('attendance_rate')
                    ->label(__('general.attendance_rate'))
                    ->getStateUsing(function (Registration $record): string {
                        $summary = $this->absenceMap()[$record->id] ?? null;

                        if ($summary === null || $summary['sessions'] === 0) {
                            return '—';
                        }

                        $attended = $summary['sessions'] - $summary['absent'];
                        $rate = number_format(($attended / $summary['sessions']) * 100, 0);
                        
                        return "{$rate}% ({$attended}/{$summary['sessions']})";
                    })
                    ->badge()
                    ->color(function (Registration $record): string {
                        $summary = $this->absenceMap()[$record->id] ?? null;

                        if ($summary === null || $summary['sessions'] === 0) {
                            return 'gray';
                        }

                        return (($summary['sessions'] - $summary['absent']) / $summary['sessions']) * 100 < 75
                            ? 'danger'
                            : 'success';
                    }),
                    
                TextColumn::make('absent_count')
                    ->label(__('general.attendance_status_absent'))
                    ->getStateUsing(fn (Registration $record): string => (string) ($this->absenceMap()[$record->id]['absent'] ?? '0'))
                    ->badge()
                    ->color('danger'),
                    
                TextColumn::make('late_count')
                    ->label(__('general.attendance_status_late'))
                    ->getStateUsing(fn (Registration $record): string => (string) ($this->absenceMap()[$record->id]['late'] ?? '0'))
                    ->badge()
                    ->color('warning'),
                    
                TextColumn::make('excused_count')
                    ->label(__('general.attendance_status_excused'))
                    ->getStateUsing(fn (Registration $record): string => (string) ($this->absenceMap()[$record->id]['excused'] ?? '0'))
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('manageAttendance')
                    ->label(__('general.manage_attendance'))
                    ->icon('heroicon-m-calendar-days')
                    ->color('primary')
                    ->modalHeading(__('general.manage_attendance'))
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalContent(fn (Registration $record) => view('filament.pages.student-attendance-modal', [
                        'registration' => $record,
                        'validDates' => $this->getValidClassDates(),
                        'attendanceMap' => $this->getStudentAttendanceMap($record->id),
                    ])),
            ])
            ->emptyStateHeading(__('general.select_batch'))
            ->emptyStateDescription(__('general.select_batch_hint'));
    }

    #[Computed]
    public function absenceMap(): array
    {
        $batchId = (int) ($this->data['course_batch_id'] ?? 0);
        if ($batchId <= 0) {
            return [];
        }

        return AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.course_batch_id', $batchId)
            ->selectRaw(
                'attendance_records.registration_id,
                 count(*) as sessions,
                 sum(case when attendance_records.status = "absent" then 1 else 0 end) as absent,
                 sum(case when attendance_records.status = "late" then 1 else 0 end) as late,
                 sum(case when attendance_records.status = "excused" then 1 else 0 end) as excused'
            )
            ->groupBy('attendance_records.registration_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->registration_id => [
                    'sessions' => (int) $row->sessions,
                    'absent' => (int) $row->absent,
                    'late' => (int) $row->late,
                    'excused' => (int) $row->excused,
                ],
            ])
            ->all();
    }

    private function getValidClassDates(): array
    {
        $batchId = (int) ($this->data['course_batch_id'] ?? 0);
        if ($batchId <= 0) {
            return [];
        }

        $batch = CourseBatch::with('periods')->find($batchId);
        if ($batch === null || ! $batch->start_date) {
            return [];
        }

        $start = \Carbon\Carbon::parse($batch->start_date)->startOfDay();
        $end = \Carbon\Carbon::today();
        
        if ($batch->end_date && $batch->end_date->lt($end)) {
            $end = $batch->end_date->startOfDay();
        } elseif ($batch->expected_end && \Carbon\Carbon::parse($batch->expected_end)->lt($end)) {
            $end = \Carbon\Carbon::parse($batch->expected_end)->startOfDay();
        }

        $periodDays = $batch->periods->pluck('days')->flatten()->unique()->toArray();
        $map = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
        $validDayOfWeek = array_map(fn($d) => $map[$d], $periodDays);

        $dates = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            if (empty($validDayOfWeek) || in_array($current->dayOfWeek, $validDayOfWeek)) {
                $dates[] = $current->format('Y-m-d');
            }
            $current->addDay();
        }

        return array_reverse($dates);
    }

    private function getStudentAttendanceMap(int $registrationId): array
    {
        $batchId = (int) ($this->data['course_batch_id'] ?? 0);
        if ($batchId <= 0) {
            return [];
        }

        return AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->leftJoin('users', 'users.id', '=', 'attendance_records.corrected_by')
            ->where('attendance_sessions.course_batch_id', $batchId)
            ->where('attendance_records.registration_id', $registrationId)
            ->select(
                'attendance_sessions.date', 
                'attendance_sessions.id as session_id', 
                'attendance_records.status', 
                'attendance_records.id as record_id',
                'attendance_records.corrected_at',
                'attendance_records.note',
                'attendance_records.change_reason',
                'users.name as corrected_by_name'
            )
            ->get()
            ->mapWithKeys(fn ($row) => [
                \Carbon\Carbon::parse($row->date)->format('Y-m-d') => [
                    'session_id' => $row->session_id,
                    'record_id' => $row->record_id,
                    'status' => $row->status,
                    'note' => $row->note,
                    'change_reason' => $row->change_reason,
                    'corrected_at' => $row->corrected_at ? \Carbon\Carbon::parse($row->corrected_at)->format('Y-m-d H:i') : null,
                    'corrected_by_name' => $row->corrected_by_name,
                ]
            ])
            ->all();
    }

    public function markAttendance(int $registrationId, string $date, string $status, ?string $note = null, ?string $changeReason = null): void
    {
        $batchId = (int) ($this->data['course_batch_id'] ?? 0);
        $batch = CourseBatch::find($batchId);
        $registration = Registration::find($registrationId);

        if (! $batch || ! $registration) {
            return;
        }

        // Find or create session for this date
        $session = AttendanceSession::where('course_batch_id', $batchId)
            ->where('date', $date)
            ->first();

        if (! $session) {
            // Business rule: creating a session defaults everyone to present
            $session = app(AttendanceService::class)->createSession(
                $batch,
                $date,
                null, // period_id not strict required here
                null, // notes
                (int) auth()->id(),
            );
        }

        app(AttendanceService::class)->recordStatus(
            $session,
            $registration,
            $status,
            (int) auth()->id(),
            $note,
            $changeReason
        );

        \Filament\Notifications\Notification::make()
            ->title(__('general.attendance_recorded'))
            ->success()
            ->send();
    }

    public function editAttendanceNoteAlpine(int $recordId, ?string $note = null, ?string $changeReason = null): void
    {
        $record = AttendanceRecord::find($recordId);
        if ($record) {
            $record->update([
                'note' => $note,
                'change_reason' => $changeReason,
                'corrected_at' => now(),
                'corrected_by' => auth()->id(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title(__('general.note_updated'))
                ->success()
                ->send();
        }
    }
}