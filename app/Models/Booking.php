<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = ['slot_id', 'student_id', 'project_id', 'questions', 'status', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AdvisorSlot::class, 'slot_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function guidanceRecord(): HasOne
    {
        return $this->hasOne(GuidanceRecord::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'booked';
    }
}
