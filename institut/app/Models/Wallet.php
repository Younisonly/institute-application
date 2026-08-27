<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'provider', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function account(): MorphOne
    {
        return $this->morphOne(Account::class, 'place');
    }

    public function getAccountId(): ?int
    {
        return $this->account?->id;
    }
}
