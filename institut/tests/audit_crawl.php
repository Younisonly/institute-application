<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = \App\Models\User::query()->where('email', 'admin@institute.local')->firstOrFail();

$routes = [
    'dashboard' => '/admin',
    'payments' => '/admin/payments',
    'finances' => '/admin/finances',
    'opening-balances' => '/admin/opening-balances',
    'settings' => '/admin/institute-settings',
    'registration-lists-report' => '/admin/registration-lists-report',
    'daily-cash-report' => '/admin/daily-cash-report',
    'profit-report' => '/admin/profit-report',
    'salary-sheet-report' => '/admin/salary-sheet-report',
    'arrears-report' => '/admin/arrears-report',
    'id-cards-report' => '/admin/id-cards-report',
    'account-ledger' => '/admin/account-ledger',
    'trial-balance' => '/admin/trial-balance',
    'income-statement' => '/admin/income-statement',
    'balance-sheet' => '/admin/balance-sheet',
    'students' => '/admin/students',
    'students-create' => '/admin/students/create',
    'registrations' => '/admin/registrations',
    'registrations-create' => '/admin/registrations/create',
    'staff' => '/admin/staff',
    'staff-create' => '/admin/staff/create',
    'books' => '/admin/books',
    'books-create' => '/admin/books/create',
    'items' => '/admin/items',
    'items-create' => '/admin/items/create',
    'item-categories' => '/admin/item-categories',
    'item-categories-create' => '/admin/item-categories/create',
    'courses' => '/admin/courses',
    'courses-create' => '/admin/courses/create',
    'expenses' => '/admin/expenses',
    'expenses-create' => '/admin/expenses/create',
    'expense-categories' => '/admin/expense-categories',
    'income-categories' => '/admin/income-categories',
    'suppliers' => '/admin/suppliers',
    'suppliers-create' => '/admin/suppliers/create',
    'other-people' => '/admin/other-people',
    'other-people-create' => '/admin/other-people/create',
    'banks' => '/admin/banks',
    'wallets' => '/admin/wallets',
    'transfers' => '/admin/transfers',
    'journals' => '/admin/journals',
    'program-types' => '/admin/program-types',
    'job-titles' => '/admin/job-titles',
    'users' => '/admin/users',
    'party-types' => '/admin/party-types',
];

foreach ($routes as $name => $url) {
    try {
        $response = $app->get('router')->dispatch(
            \Illuminate\Http\Request::create($url, 'GET')
        );
        $status = $response->getStatusCode();
        $class = $response->exception ? get_class($response->exception) : '-';
        $msg = $response->exception ? mb_substr($response->exception->getMessage(), 0, 120) : '';
        echo str_pad($name, 26).' '.$status.' '.$class.' '.$msg.PHP_EOL;
    } catch (\Throwable $e) {
        echo str_pad($name, 26).' EXCEPTION '.get_class($e).' '.mb_substr($e->getMessage(), 0, 160).PHP_EOL;
    }
}
