<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bank;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public const CODE_CASH = '1100';

    public const CODE_INVENTORY_BOOKS = '1510';

    public const CODE_INVENTORY_ITEMS = '1520';

    public const CODE_SUPPLIER_PAYABLE = '2110';

    public const CODE_STAFF_ADVANCES = '1420';

    public const CODE_CAPITAL = '3100';

    public const CODE_RETAINED_EARNINGS = '3200';

    public const CODE_INCOME_COURSE_FEES = '4100';

    public const CODE_INCOME_BOOKS = '4200';

    public const CODE_INCOME_ITEMS = '4300';

    public const CODE_INCOME_OTHER = '4400';

    public const CODE_EXPENSE_SALARIES = '5100';

    public const CODE_EXPENSE_OTHER = '5900';

    public function cashAccount(): Account
    {
        return Account::query()->where('code', self::CODE_CASH)->firstOrFail();
    }

    public function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    public function ensureForPlace(Bank|Wallet $place): Account
    {
        return DB::transaction(function () use ($place): Account {
            $account = Account::query()
                ->where('place_type', $place->getMorphClass())
                ->where('place_id', $place->id)
                ->lockForUpdate()
                ->first();

            if ($account) {
                $place->setRelation('account', $account);

                return $account;
            }

            $code = (string) (($place instanceof Bank ? 1200 : 1300) + $place->id);

            while (Account::query()->where('code', $code)->lockForUpdate()->exists()) {
                $code = (string) ((int) $code + 1);
            }

            $account = Account::query()->create([
                'code' => $code,
                'name_ar' => $place->name,
                'name_en' => $place->name,
                'type' => Account::TYPE_ASSET,
                'place_type' => $place->getMorphClass(),
                'place_id' => $place->id,
            ]);
            $place->setRelation('account', $account);

            return $account;
        });
    }

    public function ensureForExpenseCategory(ExpenseCategory $category): Account
    {
        return DB::transaction(function () use ($category): Account {
            $freshCategory = $category->fresh();
            if ($freshCategory && $freshCategory->account_id) {
                return $freshCategory->account;
            }

            $baseCode = 5900 + $category->id;
            $code = (string) $baseCode;

            while (Account::query()->where('code', $code)->lockForUpdate()->exists()) {
                $code = (string) ((int) $code + 1);
            }

            $account = Account::query()->create([
                'code' => $code,
                'name_ar' => $category->name,
                'name_en' => $category->name,
                'type' => Account::TYPE_EXPENSE,
            ]);
            $category->update(['account_id' => $account->id]);
            $category->setRelation('account', $account);

            return $account;
        });
    }

    public function ensureForIncomeCategory(IncomeCategory $category): Account
    {
        return DB::transaction(function () use ($category): Account {
            $freshCategory = $category->fresh();
            if ($freshCategory && $freshCategory->account_id) {
                return $freshCategory->account;
            }

            $baseCode = 4400 + $category->id;
            $code = (string) $baseCode;

            while (Account::query()->where('code', $code)->lockForUpdate()->exists()) {
                $code = (string) ((int) $code + 1);
            }

            $account = Account::query()->create([
                'code' => $code,
                'name_ar' => $category->name,
                'name_en' => $category->name,
                'type' => Account::TYPE_INCOME,
            ]);
            $category->update(['account_id' => $account->id]);
            $category->setRelation('account', $account);

            return $account;
        });
    }
}
