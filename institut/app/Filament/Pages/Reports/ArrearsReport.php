<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Forms\Components\PaymentDetails;
use App\Models\Registration;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Services\ReceiptNumberService;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArrearsReport extends Page implements HasTable
{

    public function getTitle(): string
    {
        return __('general.arrears_report');
    }

    use HasRbac, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reports.arrears';

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.arrears_report');
    }

    public function getReport(): array
    {
        $rows = app(ReportService::class)->arrears();

        return [
            'rows' => $rows,
            'total' => (float) $rows->sum(fn ($student): float => $student->balance),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Student::query()
                ->withBalance()
                ->with(['registrations' => fn ($q) => $q->where('status', 'active')->with('course')])
                ->havingRaw('(COALESCE(charges, 0) - COALESCE(payments, 0) + COALESCE(refunds, 0)) > 0')
                ->orderByDesc('charges'))
            ->columns([
                TextColumn::make('student_code')->label(__('general.student_code'))->placeholder('—')->color('gray'),
                TextColumn::make('name')->label(__('general.student'))->searchable()->weight('semibold'),
                TextColumn::make('phone')->label(__('general.phone'))->placeholder('—'),
                TextColumn::make('guardian_phone')->label(__('general.guardian_phone'))->placeholder('—'),
                TextColumn::make('registrations')
                    ->label(__('general.course'))
                    ->formatStateUsing(fn (Student $record): string => $record->registrations->map(fn ($r): string => $r->course?->name)->implode('، ') ?: '—')
                    ->placeholder('—'),
                TextColumn::make('balance')
                    ->label(__('general.remaining'))
                    ->alignment(Alignment::End)
                    ->weight('bold')
                    ->color('danger')
                    ->state(fn (Student $record): string => \App\Helpers\MoneyFormatter::formatStudentBalance((float) $record->balance))
                    ->summarize(
                        Summarizer::make()
                            ->label(__('general.total'))
                            ->using(fn ($query): float => (float) $query->get()->sum('balance'))
                            ->formatStateUsing(fn (float $state): string => \App\Helpers\MoneyFormatter::formatStudentBalance($state, true))
                    ),
            ])
            ->actions([
                TableAction::make('recordPayment')
                    ->label(__('general.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'accountant', 'registrar']) ?? false)
                    ->modalHeading(__('general.record_payment'))
                    ->form([
                        Select::make('registration_id')->native(false)
                            ->label(__('general.registration'))
                            ->options(fn (?Student $record): array => $record !== null
                                ? Registration::query()
                                    ->where('student_id', $record->id)
                                    ->whereIn('status', ['active', 'suspended'])
                                    ->with('course')
                                    ->get()
                                    ->mapWithKeys(fn (Registration $r): array => [
                                        $r->id => $r->course?->name.' — '.$r->start_month.' • '.number_format($r->balance).' '.__('general.currency'),
                                    ])
                                    ->all()
                                : [])
                            ->searchable()
                            ->required(),
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                        DatePicker::make('date')->label(__('general.date'))->default(now())->displayFormat('d/m/Y')->required(),
                        ...PaymentDetails::fields(),
                        \Filament\Forms\Components\TextInput::make('description')->label(__('general.description'))->maxLength(255),
                    ])
                    ->action(function (?Student $record, array $data): void {
                        if ($record === null) {
                            throw ValidationException::withMessages(['record' => __('general.record_not_found')]);
                        }

                        DB::transaction(function () use ($record, $data): void {
                            StudentTransaction::create([
                                'student_id' => $record->id,
                                'registration_id' => $data['registration_id'],
                                'type' => 'payment',
                                'amount' => $data['amount'],
                                'date' => $data['date'],
                                'method' => $data['method'] ?? 'cash',
                                'bank_id' => $data['bank_id'] ?? null,
                                'wallet_id' => $data['wallet_id'] ?? null,
                                'transaction_ref' => $data['transaction_ref'] ?? null,
                                'receipt_no' => app(ReceiptNumberService::class)->next(),
                                'description' => $data['description'] ?? null,
                                'created_by' => Auth::id(),
                            ]);
                        });

                        Notification::make()->title(__('general.payment_recorded'))->success()->send();
                    }),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.arrears.print'))
                ->openUrlInNewTab(),
        ];
    }
}
