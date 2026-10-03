<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdvisorSlot extends Model
{
    protected $fillable = ['advisor_id', 'start_at', 'end_at', 'status'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'slot_id');
    }

    public function isBookable(): bool
    {
        return $this->status === 'open';
    }
}
