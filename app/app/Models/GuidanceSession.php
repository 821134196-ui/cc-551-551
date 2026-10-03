<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GuidanceSession extends Model
{
    protected $fillable = [
        'appointment_id', 'submission_id', 'advisor_id', 'student_id', 'overall_comment',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** 本评语针对的稿件版本 —— 创建后固定，不随后续撤回/新版改变 */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function feedbackItems(): HasMany
    {
        return $this->hasMany(FeedbackItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
