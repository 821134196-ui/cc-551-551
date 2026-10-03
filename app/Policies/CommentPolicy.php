<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /** 可见性由其所属课题决定：课题学生或其导师 */
    public function view(User $user, Comment $comment): bool
    {
        $project = $comment->guidanceRecord->project;

        return $project->student_id === $user->id
            || $project->advisor_id === $user->id;
    }

    /** 只有提出该评语的导师可以关闭/重开，学生无权 */
    public function manage(User $user, Comment $comment): bool
    {
        return $user->isAdvisor() && $comment->advisor_id === $user->id;
    }
}
