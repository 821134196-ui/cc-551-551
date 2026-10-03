<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved'; // 学生已回应，待导师确认
    public const STATUS_CLOSED = 'closed';     // 导师确认关闭

    protected $fillable = [
        'guidance_record_id', 'advisor_id', 'content', 'status', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function guidanceRecord(): BelongsTo
    {
        return $this->belongsTo(GuidanceRecord::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CommentResponse::class)->orderBy('created_at');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_CLOSED;
    }
}
