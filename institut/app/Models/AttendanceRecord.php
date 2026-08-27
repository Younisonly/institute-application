<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = [
        'attendance_session_id',
        'registration_id',
        'status',
        'note',
        'change_reason',
        'corrected_at',
        'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'corrected_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}