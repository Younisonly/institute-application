<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Filament\Forms\Components\MonthPicker;
use App\Models\InstituteSetting;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Process;

class InstituteSettings extends Page implements HasForms
{
    use HasRbac, InteractsWithForms;

    protected static function accessRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('general.settings');
    }

    public function getTitle(): string
    {
        return __('general.settings');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_settings');
    }

    protected static string $view = 'filament.pages.institute-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(InstituteSetting::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        \Filament\Forms\Components\Tabs\Tab::make(__('general.tab_general'))
                            ->schema([
                                TextInput::make('name_ar')->label(__('general.settings_name_ar'))->required(),
                                TextInput::make('name_en')->label(__('general.settings_name_en'))->required(),
                                Select::make('institute_type')->native(false)
                                    ->label(__('general.settings_institute_type'))
                                    ->options([
                                        'language' => __('general.institute_type_language'),
                                        'computer' => __('general.institute_type_computer'),
                                        'vocational' => __('general.institute_type_vocational'),
                                        'art' => __('general.institute_type_art'),
                                        'religious' => __('general.institute_type_religious'),
                                        'other' => __('general.institute_type_other'),
                                    ]),
                                TextInput::make('founded_year')->label(__('general.settings_founded_year'))->numeric()->minValue(1900)->maxValue(2100),
                                FileUpload::make('logo_path')->label(__('general.settings_logo'))->image()->directory('institute')->columnSpanFull(),
                            ])->columns(2),
                        \Filament\Forms\Components\Tabs\Tab::make(__('general.tab_contact'))
                            ->schema([
                                TextInput::make('phone')->label(__('general.phone')),
                                TextInput::make('email')->label(__('general.email'))->email(),
                                TextInput::make('website')->label(__('general.settings_website'))->url(),
                                TextInput::make('address')->label(__('general.settings_address'))->columnSpanFull(),
                            ])->columns(2),
                        \Filament\Forms\Components\Tabs\Tab::make(__('general.tab_accounting'))
                            ->schema([
                                TextInput::make('currency_label')->label(__('general.settings_currency_label'))->default('YER'),
                                MonthPicker::make('current_month')->label(__('general.settings_current_month')),
                                TextInput::make('receipt_next_no')->label(__('general.settings_receipt_next_no'))->numeric()->maxValue(999999999999),
                                DatePicker::make('financial_lock_date')
                                    ->label(__('general.financial_lock_date'))
                                    ->displayFormat('d/m/Y')
                                    ->helperText(__('general.financial_lock_date_hint')),
                            ])->columns(2),
                    ])
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('general.save'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $settings = InstituteSetting::current();
        $settings->update($this->form->getState());

        Notification::make()->title(__('general.saved'))->success()->send();
    }

    public function downloadBackupAction(): Action
    {
        return Action::make('downloadBackup')
            ->label(__('general.settings_download_backup'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->authorize(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
            ->action(function () {
                $file = $this->createBackupFile();

                if ($file === null) {
                    Notification::make()->title(__('general.settings_backup_failed'))->danger()->send();

                    return;
                }

                return response()->download($file);
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadBackupAction(),
            $this->advanceMonthAction(),
        ];
    }

    public function advanceMonthAction(): Action
    {
        return Action::make('advanceMonth')
            ->label(__('general.advance_month'))
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->requiresConfirmation()
            ->authorize(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
            ->action(function (): void {
                $settings = InstituteSetting::current();
                $current = $settings->current_month ?: now()->format('Y-m');
                $next = CarbonImmutable::createFromFormat('Y-m', $current)->addMonth()->format('Y-m');
                $settings->update(['current_month' => $next]);

                $this->form->fill($settings->fresh()->toArray());

                Notification::make()
                    ->title(__('general.advance_month').' → '.$next)
                    ->success()
                    ->send();
            });
    }

    private function createBackupFile(): ?string
    {
        $settings = InstituteSetting::current();
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.'/'.now()->format('Y-m-d_His').'.sql';
        $command = sprintf(
            'mysqldump --host=%s --user=%s %s %s > %s 2>/dev/null',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? '--password='.escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($file)
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            return null;
        }

        $backups = glob($dir.'/*.sql') ?: [];

        if (count($backups) > 10) {
            usort($backups, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

            foreach (array_slice($backups, 10) as $old) {
                @unlink($old);
            }
        }

        return $file;
    }
}
