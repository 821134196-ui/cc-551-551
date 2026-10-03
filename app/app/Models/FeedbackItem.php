<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackItem extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'guidance_session_id', 'advisor_id', 'student_id',
        'sequence', 'content', 'status', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function guidanceSession(): BelongsTo
    {
        return $this->belongsTo(GuidanceSession::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class);
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }
}
