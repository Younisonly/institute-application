<?php

namespace Tests\Feature;

use App\Helpers\MoneyFormatter;
use Tests\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_money_formatter_formats_student_balance_correctly(): void
    {
        app()->setLocale('ar');
        $this->assertEquals('15,000 ريال (عليه)', MoneyFormatter::formatStudentBalance(15000));
        $this->assertEquals('15,000 ريال (لكم)', MoneyFormatter::formatStudentBalance(15000, true));
        $this->assertEquals('5,000 ريال (له)', MoneyFormatter::formatStudentBalance(-5000));
        $this->assertEquals('0 ريال', MoneyFormatter::formatStudentBalance(0));

        app()->setLocale('en');
        $this->assertEquals('15,000 YER (On him)', MoneyFormatter::formatStudentBalance(15000));
        $this->assertEquals('15,000 YER (For you)', MoneyFormatter::formatStudentBalance(15000, true));
        $this->assertEquals('5,000 YER (For him)', MoneyFormatter::formatStudentBalance(-5000));
    }

    public function test_money_formatter_formats_supplier_balance_correctly(): void
    {
        app()->setLocale('ar');
        $this->assertEquals('80,000 ريال (له)', MoneyFormatter::formatSupplierBalance(80000));
        $this->assertEquals('80,000 ريال (عليكم)', MoneyFormatter::formatSupplierBalance(80000, true));
        $this->assertEquals('10,000 ريال (عليه)', MoneyFormatter::formatSupplierBalance(-10000));

        app()->setLocale('en');
        $this->assertEquals('80,000 YER (For him)', MoneyFormatter::formatSupplierBalance(80000));
        $this->assertEquals('80,000 YER (On you)', MoneyFormatter::formatSupplierBalance(80000, true));
        $this->assertEquals('10,000 YER (On him)', MoneyFormatter::formatSupplierBalance(-10000));
    }

    public function test_money_formatter_formats_other_person_balance_correctly(): void
    {
        app()->setLocale('ar');
        $this->assertEquals('40,000 ريال (له)', MoneyFormatter::formatOtherPersonBalance(40000));
        $this->assertEquals('40,000 ريال (عليكم)', MoneyFormatter::formatOtherPersonBalance(40000, true));
        $this->assertEquals('5,000 ريال (عليه)', MoneyFormatter::formatOtherPersonBalance(-5000));
    }

    public function test_money_formatter_formats_account_balance_correctly(): void
    {
        app()->setLocale('ar');
        $this->assertEquals('100,000 ريال (مدين)', MoneyFormatter::formatAccountBalance(100000, 'asset'));
        $this->assertEquals('50,000 ريال (دائن)', MoneyFormatter::formatAccountBalance(50000, 'liability'));
    }
}
