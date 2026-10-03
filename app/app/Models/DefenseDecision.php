<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseDecision extends Model
{
    protected $fillable = [
        'student_id', 'advisor_id', 'final_submission_id',
        'eligible', 'comment', 'unresolved_count', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible' => 'boolean',
            'unresolved_count' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function finalSubmission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'final_submission_id');
    }
}
