<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * 课题访问策略：只有课题学生本人与其绑定导师可以查看。
 * 其他导师（即使也是导师角色）一律无权访问，控制器统一 403。
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->student_id === $user->id
            || $project->advisor_id === $user->id;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->student_id === $user->id;
    }

    public function submitManuscript(User $user, Project $project): bool
    {
        return $project->student_id === $user->id && $user->isStudent();
    }

    public function giveGuidance(User $user, Project $project): bool
    {
        return $project->advisor_id === $user->id && $user->isAdvisor();
    }

    public function decideEligibility(User $user, Project $project): bool
    {
        return $project->advisor_id === $user->id && $user->isAdvisor();
    }
}
