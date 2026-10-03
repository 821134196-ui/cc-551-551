<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TimeSlot extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'advisor_id', 'start_at', 'end_at', 'location', 'status', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function activeAppointment(): HasOne
    {
        return $this->hasOne(Appointment::class)
            ->whereIn('status', [Appointment::STATUS_BOOKED, Appointment::STATUS_COMPLETED]);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function isBookable(): bool
    {
        return $this->status === self::STATUS_OPEN
            && ! $this->activeAppointment()->exists()
            && $this->start_at->isFuture();
    }
}
