<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->runningInConsole() && request()->getHttpHost()) {
            config(['app.url' => request()->schemeAndHttpHost()]);
            \Illuminate\Support\Facades\URL::forceRootUrl(request()->schemeAndHttpHost());
        }

        \App\Models\Staff::observe(\App\Observers\StaffObserver::class);
        \App\Models\Supplier::observe(\App\Observers\SupplierObserver::class);
        \App\Models\OtherPerson::observe(\App\Observers\OtherPersonObserver::class);
        \App\Models\Student::observe(\App\Observers\StudentObserver::class);
        \App\Models\Registration::observe(\App\Observers\RegistrationObserver::class);
        \App\Models\StudentTransaction::observe(\App\Observers\StudentTransactionObserver::class);
        \App\Models\StaffTransaction::observe(\App\Observers\StaffTransactionObserver::class);
        \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);
        \App\Models\SupplierTransaction::observe(\App\Observers\SupplierTransactionObserver::class);
        \App\Models\OtherPeopleTransaction::observe(\App\Observers\OtherPeopleTransactionObserver::class);
        \App\Models\Transfer::observe(\App\Observers\TransferObserver::class);
        \App\Models\Bank::observe(\App\Observers\BankObserver::class);
        \App\Models\Wallet::observe(\App\Observers\WalletObserver::class);
        \App\Models\ExpenseCategory::observe(\App\Observers\ExpenseCategoryObserver::class);
        \App\Models\IncomeCategory::observe(\App\Observers\IncomeCategoryObserver::class);

        \Illuminate\Support\Facades\View::composer('prints.*', function (\Illuminate\View\View $view): void {
            $view->with('settings', \App\Models\InstituteSetting::current());
        });
    }
}
