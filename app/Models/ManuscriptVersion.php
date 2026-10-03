<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ManuscriptVersion extends Model
{
    protected $fillable = [
        'project_id', 'student_id', 'booking_id', 'version_no',
        'title', 'note', 'status', 'withdrawn_at', 'withdraw_reason', 'is_final',
    ];

    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
            'is_final' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function guidanceRecords(): HasMany
    {
        return $this->hasMany(GuidanceRecord::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CommentResponse::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    /** 是否允许撤回：尚未被任何指导记录引用才可撤回 */
    public function canWithdraw(): bool
    {
        return $this->isActive() && !$this->guidanceRecords()->exists();
    }
}
