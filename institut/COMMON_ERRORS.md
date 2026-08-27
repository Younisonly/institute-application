# Common Errors & Troubleshooting Guide — Institute Management System

This document explains common runtime errors encountered in the Institute Management System (Laravel 12, Filament 3, Livewire 3), their underlying root causes, and standard procedures for fixing them.

---

## 1. Livewire 419 Dialog: "This page has expired. Would you like to refresh the page?"

### Symptoms
When navigating to certain admin panel pages (such as `/admin/finances` or Student Details `/admin/students/{id}`), a repeating browser dialog pops up stating:
> *This page has expired. Would you like to refresh the page?*

Clicking "Refresh" reloads the page, but the dialog pops up again shortly after.

---

### Root Causes

#### Cause A: Background Lazy-Loading & Polling on Widgets
Filament 3 widgets (subclasses of `TableWidget`, `StatsOverviewWidget`, or `Widget`) default to **lazy-loading** (`$isLazy = true`) and default polling intervals (`$pollingInterval = '15s'`).
- Upon page load, Filament renders placeholder HTML and instructs Livewire JS in the browser to make immediate background AJAX requests (`POST /livewire/update`) to mount and poll widget data.
- If an AJAX request fails (due to CSRF token mismatch, session expiry, or component payload hydration errors), Livewire JS catches the HTTP `419` status code and displays the alert popup.

#### Cause B: Storing Eloquent Model Objects in Closure Scopes
When building custom Filament tables or widgets, capturing full Eloquent Model instances inside column closure variables (e.g. mapping rows containing `$row['account']` or `$row['model']`) causes Livewire snapshot verification and checksum mismatch during hydration. When Livewire fails to verify the component snapshot checksum, it aborts with HTTP `419 Page Expired`.

#### Cause C: Domain / Host Mismatch (`localhost` vs `127.0.0.1`)
The application `.env` config specifies `APP_URL=http://127.0.0.1:8001`. If a user logs into `http://localhost:8001` and then navigates to `http://127.0.0.1:8001` (or vice-versa), browser cookie security policies reject session cookies on AJAX requests, producing immediate HTTP 419 CSRF failures.

---

### Standard Fixes (Developer Rules)

#### Rule 1: Disable Lazy Loading and Polling on All Widgets
To ensure widgets render 100% synchronously with the initial page server HTML (eliminating background AJAX calls on load), explicitly declare `$isLazy = false` and `$pollingInterval = null` in every widget class:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget;

class MyCustomWidget extends TableWidget
{
    // Prevent background AJAX lazy-loading on page render
    protected static bool $isLazy = false;

    // Prevent periodic background AJAX polling
    protected static ?string $pollingInterval = null;
    
    // ...
}
```

#### Rule 2: Pass Only Scalar / Primitive Values into Closures
Never pass full Eloquent Model objects inside arrays/collections used by table column state closures. Always extract primitive values (`int`, `float`, `string`):

```php
// ❌ WRONG: Stores Eloquent Model object inside closure scope
private function getRows(): Collection
{
    return collect($reportData)->mapWithKeys(fn (array $row) => [
        $row['account']->id => $row // $row contains 'account' => Account model object!
    ]);
}

// ✅ CORRECT: Map primitive scalar values only
private function getRows(): Collection
{
    return collect($reportData)->mapWithKeys(fn (array $row) => [
        (int) $row['account']->id => [
            'debit' => (float) $row['debit'],
            'credit' => (float) $row['credit'],
            'balance' => (float) $row['balance'],
        ]
    ]);
}
```

#### Rule 3: Use the Exact Host URL (`http://127.0.0.1:8001`)
Always log in and use the application via `http://127.0.0.1:8001` in your browser address bar so session cookies match `APP_URL`.

---

## 2. Livewire Component State Mismatch on Resource Detail Pages

### Symptoms
Opening a view page with header widgets (such as Student Details `/admin/students/{id}` with `RecommendationsWidget`) shows a Livewire hydration error or 419 popup.

### Root Cause
Header widgets mounted on resource pages receive `$record` as a parameter (`RecommendationsWidget::make(['record' => $this->getRecord()])`). If lazy loading is enabled, the background mount request fires without the full record context.

### Standard Fix
Set `protected static bool $isLazy = false;` on the widget so it mounts synchronously with the resource view page.

---

## 3. Executive Summary of Protected Widgets

All 12 Filament widgets in `app/Filament/Widgets/` are configured with synchronous rendering (`$isLazy = false` and `$pollingInterval = null`):
1. `BatchesEndingSoonWidget`
2. `ClosedYearsWidget`
3. `LowStockWidget`
4. `MoneyPlacesWidget`
5. `MonthlyChartWidget`
6. `PartyBalancesWidget`
7. `PendingResultsWidget`
8. `RecentActivityWidget`
9. `RecommendationsWidget`
10. `RegistrationsTrendWidget`
11. `StatsOverview`
12. `TrialBalanceWidget`

---

## 4. Verification & Testing Commands

To verify all Livewire components and widgets render without 419 or hydration errors:

```bash
/usr/bin/php ./vendor/bin/phpunit --filter=Livewire
```
