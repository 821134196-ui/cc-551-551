<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Submission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'student_id', 'advisor_id', 'version', 'title', 'note',
        'status', 'withdraw_reason', 'withdrawn_at', 'previous_id',
    ];

    protected function casts(): array
    {
        return ['withdrawn_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'previous_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class);
    }

    public function guidanceSessions(): HasMany
    {
        return $this->hasMany(GuidanceSession::class);
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }
}
