<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherPerson extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'party_type_id', 'phone', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function partyType(): BelongsTo
    {
        return $this->belongsTo(PartyType::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OtherPeopleTransaction::class, 'other_person_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->transactions()
            ->whereNull('voided_at')
            ->selectRaw('SUM(CASE WHEN type = "in" THEN amount ELSE -amount END) as total')
            ->value('total') ?? 0);
    }
}
