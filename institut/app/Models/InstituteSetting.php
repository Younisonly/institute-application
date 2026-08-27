<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstituteSetting extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'address',
        'phone',
        'email',
        'logo_path',
        'currency_label',
        'current_month',
        'receipt_next_no',
        'journal_next_no',
        'certificate_next_no',
        'financial_lock_date',
        'website',
        'institute_type',
        'founded_year',
    ];

    protected function casts(): array
    {
        return [
            'financial_lock_date' => 'date',
        ];
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name_ar' => 'معهد',
            'name_en' => 'Institute',
        ]);
    }
}
