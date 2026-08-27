<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'details',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write a compact audit trail entry (voids, transfers, closes, etc.).
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Write an old -> new change entry (marks, statuses, corrections).
     */
    public static function change(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        mixed $before = null,
        mixed $after = null,
        array $details = [],
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'details' => $details ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
