<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasRbac;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Wallet;
use App\Services\AccountService;
use App\Services\FinancePostingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class OpeningBalances extends Page implements HasForms
{

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    use HasRbac, InteractsWithForms;

    protected static function accessRoles(): array
    {
        return ['admin', 'accountant'];
    }

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.opening-balances';

    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('general.nav_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.opening_balances');
    }

    public static function getModelLabel(): string
    {
        return __('general.opening_balances');
    }

    public function mount(): void
    {
        $this->form->fill([
            'entries' => [
                ['account_id' => app(AccountService::class)->cashAccount()->id, 'amount' => 0],
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('entries')
                    ->label(__('general.opening_balance_entries'))
                    ->schema([
                        \Filament\Forms\Components\Select::make('account_id')->native(false)
                            ->label(__('general.account'))
                            ->options(fn (): array => Account::query()
                                ->where('type', Account::TYPE_ASSET)
                                ->where(fn ($q) => $q->where('code', AccountService::CODE_CASH)->orWhereNotNull('place_type'))
                                ->get()
                                ->mapWithKeys(fn (Account $account): array => [$account->id => $account->name.' ('.$account->code.')'])
                                ->all())
                            ->distinct()
                            ->required(),
                        MoneyInput::make('amount')->label(__('general.amount'))->required()->minValue(1),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel(__('general.add_line')),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('post')
                ->label(__('general.post_opening_balances'))
                ->submit('postBalances'),
        ];
    }

    public function postBalances(): void
    {
        $alreadyPosted = \App\Models\JournalEntry::query()
            ->where('reference', 'opening-balance')
            ->whereNull('voided_at')
            ->exists();

        if ($alreadyPosted) {
            Notification::make()->title(__('general.error'))->body(__('general.opening_balance_already_posted'))->danger()->send();

            return;
        }

        $entries = collect($this->form->getState()['entries'] ?? [])->filter(fn (array $row): bool => ($row['amount'] ?? 0) > 0);

        if ($entries->isEmpty()) {
            Notification::make()->title(__('general.error'))->body(__('general.no_records'))->danger()->send();

            return;
        }

        $accountIds = $entries->pluck('account_id')->all();
        if (count($accountIds) !== count(array_unique($accountIds))) {
            Notification::make()->title(__('general.error'))->body(__('general.duplicate_account_in_opening_balances'))->danger()->send();

            return;
        }

        foreach ($entries as $row) {
            $accId = (int) $row['account_id'];
            $exists = \App\Models\JournalEntryLine::query()
                ->where('account_id', $accId)
                ->whereHas('entry', fn ($q) => $q->where('reference', 'opening-balance')->whereNull('voided_at'))
                ->exists();
            if ($exists) {
                Notification::make()->title(__('general.error'))->body(__('general.opening_balance_already_posted'))->danger()->send();

                return;
            }
        }

        DB::transaction(function () use ($entries): void {
            foreach ($entries as $row) {
                app(FinancePostingService::class)->postOpeningBalance((int) $row['account_id'], (float) $row['amount']);
            }
        });

        Notification::make()->title(__('general.posted'))->success()->send();
    }
}
