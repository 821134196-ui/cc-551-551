<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    protected $fillable = ['feedback_item_id', 'submission_id', 'student_id', 'reply'];

    public function feedbackItem(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class);
    }

    /** 回应所随附的新稿件版本 */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
