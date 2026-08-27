<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRbac;
use Filament\Forms\Components\TimePicker;
use App\Filament\Resources\PeriodResource\Pages;
use App\Models\Period;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeriodResource extends Resource
{
    use HasRbac;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant', 'registrar', 'teacher'];
    }

    protected static function createRoles(): array
    {
        return ['admin', 'registrar'];
    }

    protected static function editRoles(): array
    {
        return ['admin', 'registrar'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static ?string $model = Period::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string
    {
        return __('general.nav_students_courses');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.periods');
    }

    public static function getModelLabel(): string
    {
        return __('general.period');
    }

    public static function getPluralModelLabel(): string
    {
        return __('general.periods');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('general.period_details'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ar')->label(__('general.name_ar'))->required()->maxLength(255),
                        TextInput::make('name_en')->label(__('general.name_en'))->required()->maxLength(255),
                    ]),
                Section::make(__('general.period_time'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->description(__('general.period_time_hint'))
                    ->schema([
                        TimePicker::make('start_time')
                            ->label(__('general.start_time'))
                            ->seconds(false)
                            ->helperText(fn (?string $state): ?string => self::getTimeInWords($state))
                            ->live(),
                        TimePicker::make('end_time')
                            ->label(__('general.end_time'))
                            ->seconds(false)
                            ->helperText(fn (?string $state): ?string => self::getTimeInWords($state))
                            ->after('start_time')
                            ->live()
                            ->rule(function (\Filament\Forms\Get $get): \Closure {
                                return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    $start = $get('start_time');
                                    $end = is_string($value) ? $value : null;

                                    if ($start !== null && $end !== null && $end <= $start) {
                                        $fail(__('general.end_after_start'));
                                    }
                                };
                            }),
                        \Filament\Forms\Components\Placeholder::make('_duration')
                            ->label(__('general.duration'))
                            ->content(function (\Filament\Forms\Get $get): string {
                                $start = $get('start_time');
                                $end = $get('end_time');

                                if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
                                    return __('general.duration_missing');
                                }

                                $startMinutes = \Illuminate\Support\Carbon::parse($start);
                                $endMinutes = \Illuminate\Support\Carbon::parse($end);

                                if ($endMinutes <= $startMinutes) {
                                    return __('general.duration_missing');
                                }

                                $diff = $startMinutes->diffInMinutes($endMinutes);

                                return $diff >= 60
                                    ? intdiv($diff, 60).' '.__('general.hours_short').' '.($diff % 60).' '.__('general.minutes_short')
                                    : $diff.' '.__('general.minutes_short');
                            })
                            ->columnSpanFull(),
                    ]),
                \Filament\Forms\Components\Section::make(__('general.period_schedule'))
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('days')
                            ->label(__('general.days'))
                            ->columns(4)
                            ->columnSpanFull()
                            ->options([
                                'sat' => __('general.sat'),
                                'sun' => __('general.sun'),
                                'mon' => __('general.mon'),
                                'tue' => __('general.tue'),
                                'wed' => __('general.wed'),
                                'thu' => __('general.thu'),
                                'fri' => __('general.fri'),
                            ]),
                        Textarea::make('notes')
                            ->label(__('general.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')->label(__('general.active'))->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(query: fn ($query, $search) => $query
                        ->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%"))
                    ->description(fn (Period $record): string => app()->getLocale() === 'ar' ? (string) $record->name_en : (string) $record->name_ar)
                    ->weight('semibold')
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-48']),
                TextColumn::make('times_label')
                    ->label(__('general.period_time'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('days_label')
                    ->label(__('general.days'))
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-48 max-w-sm']),
                TextColumn::make('batches_count')
                    ->label(__('general.batches'))
                    ->counts('batches')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label(__('general.active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label(__('general.trashed'))
                    ->placeholder(__('general.without_trashed'))
                    ->trueLabel(__('general.with_trashed'))
                    ->falseLabel(__('general.only_trashed')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription(__('general.period_delete_hint')),
                Tables\Actions\RestoreAction::make()->label(__('general.restore')),
                Tables\Actions\ForceDeleteAction::make()
                    ->label(__('general.force_delete'))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()->label(__('general.restore')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label(__('general.force_delete'))
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeriods::route('/'),
            'create' => Pages\CreatePeriod::route('/create'),
            'edit' => Pages\EditPeriod::route('/{record}/edit'),
        ];
    }

    public static function getTimeInWords(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            $carbon = \Illuminate\Support\Carbon::parse($time);
        } catch (\Exception $e) {
            return null;
        }

        $hour = $carbon->format('g');
        $minute = (int) $carbon->format('i');
        $isPm = $carbon->format('a') === 'pm';

        $locale = app()->getLocale();

        if ($locale === 'ar') {
            $hoursAr = [
                1 => 'الواحدة', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 
                5 => 'الخامسة', 6 => 'السادسة', 7 => 'السابعة', 8 => 'الثامنة', 
                9 => 'التاسعة', 10 => 'العاشرة', 11 => 'الحادية عشرة', 12 => 'الثانية عشرة'
            ];
            
            $amPmAr = $isPm ? 'مساءً' : 'صباحاً';
            $h = (int) $hour;
            $nextH = $h === 12 ? 1 : $h + 1;
            
            $minuteStr = '';
            if ($minute === 0) {
                $minuteStr = 'تماماً';
                $hourStr = $hoursAr[$h] ?? $hoursAr[1];
            } elseif ($minute === 15) {
                $minuteStr = 'والربع';
                $hourStr = $hoursAr[$h] ?? $hoursAr[1];
            } elseif ($minute === 20) {
                $minuteStr = 'والثلث';
                $hourStr = $hoursAr[$h] ?? $hoursAr[1];
            } elseif ($minute === 30) {
                $minuteStr = 'والنصف';
                $hourStr = $hoursAr[$h] ?? $hoursAr[1];
            } elseif ($minute === 40) {
                $minuteStr = 'إلا ثلث';
                $hourStr = $hoursAr[$nextH] ?? $hoursAr[1];
            } elseif ($minute === 45) {
                $minuteStr = 'إلا ربع';
                $hourStr = $hoursAr[$nextH] ?? $hoursAr[1];
            } else {
                $f = new \NumberFormatter('ar', \NumberFormatter::SPELLOUT);
                $minuteStr = 'و ' . $f->format($minute) . ' دقيقة';
                $hourStr = $hoursAr[$h] ?? $hoursAr[1];
            }
            
            return "{$hourStr} {$minuteStr} {$amPmAr}";
        } else {
            $f = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            
            $h = (int) $hour;
            
            if ($minute > 30) {
                $h = $h === 12 ? 1 : $h + 1;
            }
            
            $hourStr = ucfirst($f->format($h));
            $amPmEn = $isPm ? 'PM' : 'AM';
            
            if ($minute === 0) {
                return "{$hourStr} o'clock {$amPmEn}";
            } elseif ($minute === 15) {
                return "Quarter past {$hourStr} {$amPmEn}";
            } elseif ($minute === 30) {
                return "Half past {$hourStr} {$amPmEn}";
            } elseif ($minute === 45) {
                return "Quarter to {$hourStr} {$amPmEn}";
            } else {
                if ($minute < 30) {
                    return ucfirst($f->format($minute)) . " past {$hourStr} {$amPmEn}";
                } else {
                    return ucfirst($f->format(60 - $minute)) . " to {$hourStr} {$amPmEn}";
                }
            }
        }
    }
}
