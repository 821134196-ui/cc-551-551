<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    public const STATUS_BOOKED = 'booked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RELEASED_BY_LEAVE = 'released_by_leave';

    protected $fillable = [
        'time_slot_id', 'student_id', 'advisor_id', 'topic',
        'status', 'submit_deadline', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'submit_deadline' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(DiscussionQuestion::class);
    }

    public function guidanceSession(): HasOne
    {
        return $this->hasOne(GuidanceSession::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_BOOKED, self::STATUS_COMPLETED], true);
    }
}
