<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentResponse extends Model
{
    protected $fillable = ['comment_id', 'student_id', 'manuscript_version_id', 'content'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function manuscriptVersion(): BelongsTo
    {
        return $this->belongsTo(ManuscriptVersion::class);
    }
}
