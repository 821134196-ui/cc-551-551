<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\GuidanceRecord;
use App\Models\ManuscriptVersion;
use App\Models\User;

/**
 * 附件下载鉴权：沿 attachable 回溯到课题，
 * 仅课题学生与其导师可下载；其他导师 403。
 */
class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        $project = match (true) {
            $attachable instanceof ManuscriptVersion => $attachable->project,
            $attachable instanceof GuidanceRecord => $attachable->project,
            default => null,
        };

        if (!$project) {
            return false;
        }

        return $project->student_id === $user->id
            || $project->advisor_id === $user->id;
    }
}
