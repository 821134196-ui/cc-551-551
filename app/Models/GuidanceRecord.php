<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 指导记录：导师约谈后针对某一稿件版本创建。
 * 一经创建不可编辑、不可删除（控制器层不提供 update/destroy）。
 * 即使所引用的稿件被撤回，记录及其评语仍原样保留并继续指向该版本。
 */
class GuidanceRecord extends Model
{
    protected $fillable = [
        'project_id', 'booking_id', 'advisor_id', 'manuscript_version_id', 'summary',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function manuscriptVersion(): BelongsTo
    {
        return $this->belongsTo(ManuscriptVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
