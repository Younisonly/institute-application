<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\HasRbac;
use App\Models\ItemCategory;
use App\Services\ReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class StockInventoryReport extends Page implements HasForms, HasTable
{

    public function getTitle(): string
    {
        return __('general.stock_inventory_report');
    }

    use HasRbac, InteractsWithForms, InteractsWithTable;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.reports.stock-inventory';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['type' => 'all']);
    }

    public static function getNavigationGroup(): string
    {
        return __('general.nav_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.stock_inventory_report');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')->native(false)
                    ->label(__('general.type'))
                    ->options([
                        'all' => __('general.all'),
                        'items' => __('general.items'),
                        'books' => __('general.books'),
                    ])
                    ->default('all')
                    ->live(),
                Select::make('category_id')->native(false)
                    ->label(__('general.category'))
                    ->options(fn (): array => ItemCategory::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['all', 'items'], true)),
                Toggle::make('low_stock_only')->label(__('general.low_stock_only'))->default(false),
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

    public function getRows(): \Illuminate\Support\Collection
    {
        $data = $this->data;

        return app(ReportService::class)->inventory(
            $data['type'] ?? 'all',
            $data['category_id'] ?? null,
            (bool) ($data['low_stock_only'] ?? false),
        );
    }

    public function table(Table $table): Table
    {
        $data = $this->data;

        return $table
            ->query(app(ReportService::class)->inventoryQuery(
                $data['type'] ?? 'all',
                $data['category_id'] ?? null,
                (bool) ($data['low_stock_only'] ?? false),
            ))
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->weight('semibold'),
                TextColumn::make('type')
                    ->label(__('general.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'book' ? __('general.book') : __('general.item'))
                    ->color(fn (string $state): string => $state === 'book' ? 'info' : 'warning'),
                TextColumn::make('category')->label(__('general.category'))->placeholder('—'),
                TextColumn::make('stock')
                    ->label(__('general.stock'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->summarize(Sum::make()->label(__('general.total'))),
                TextColumn::make('buy_price')
                    ->label(__('general.buy_price'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' '.__('general.currency')),
                TextColumn::make('sale_price')
                    ->label(__('general.sale_price'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' '.__('general.currency')),
                TextColumn::make('buy_value')
                    ->label(__('general.stock_value'))
                    ->alignment(\Filament\Support\Enums\Alignment::End)
                    ->weight('bold')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state).' '.__('general.currency'))
                    ->summarize(Sum::make()->label(__('general.total'))->formatStateUsing(fn (float $state): string => number_format($state).' '.__('general.currency'))),
                TextColumn::make('low_stock')
                    ->label(__('general.low_stock'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ((int) $state === 1) ? __('general.yes') : __('general.no'))
                    ->color(fn (mixed $state): string => ((int) $state === 1) ? 'danger' : 'success'),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        $query = array_filter([
            'type' => $this->data['type'] ?? 'all',
            'category_id' => $this->data['category_id'] ?? null,
            'low_stock_only' => $this->data['low_stock_only'] ?? false,
        ]);

        return [
            Action::make('print')
                ->label(__('general.print'))
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('reports.stock-inventory.print', $query))
                ->openUrlInNewTab(),
        ];
    }
}
