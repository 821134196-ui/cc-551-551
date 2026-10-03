<?php

namespace App\Policies;

use App\Models\AdvisorSlot;
use App\Models\User;

class AdvisorSlotPolicy
{
    public function view(User $user, AdvisorSlot $slot): bool
    {
        // 导师本人，或……学生只需看开放列表；单条详情允许登录学生看（预约选择需要）
        return $user->isAdvisor()
            ? $slot->advisor_id === $user->id
            : true;
    }

    public function manage(User $user, AdvisorSlot $slot): bool
    {
        return $user->isAdvisor() && $slot->advisor_id === $user->id;
    }
}
