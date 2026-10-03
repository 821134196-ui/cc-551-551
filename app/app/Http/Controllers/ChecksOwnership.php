<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\FeedbackItem;
use App\Models\GuidanceSession;
use App\Models\Submission;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 跨导师访问隔离的统一入口。
 * 任何资源必须满足：
 *  - 学生本人：resource.student_id == 自己
 *  - 学生的导师：resource.advisor_id == 自己
 * 其他导师 / 学生一律 403。
 */
trait ChecksOwnership
{
    protected function assertCanAccessUser(User $owner, ?User $advisor = null): void
    {
        $me = request()->user();

        if ($owner->id === $me->id) {
            return;
        }

        if ($me->isAdvisor() && $advisor?->id === $me->id) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => '无权访问该资料（跨导师访问被拒绝）。',
        ], 403));
    }

    protected function assertCanAccessAppointment(Appointment $appointment): void
    {
        $this->assertCanAccessUser($appointment->student, $appointment->advisor);
    }

    protected function assertCanAccessSlot(TimeSlot $slot): void
    {
        $me = request()->user();
        if ($me->isStudent() && $me->advisor_id === $slot->advisor_id) {
            return; // 学生查看自己导师的时段允许
        }
        if ($me->isAdvisor() && $slot->advisor_id === $me->id) {
            return;
        }
        throw new HttpResponseException(response()->json([
            'message' => '无权访问该导师时段。',
        ], 403));
    }

    protected function assertCanAccessSubmission(Submission $submission): void
    {
        $this->assertCanAccessUser($submission->student, $submission->advisor);
    }

    protected function assertCanAccessSession(GuidanceSession $session): void
    {
        $this->assertCanAccessUser($session->student, $session->advisor);
    }

    protected function assertCanAccessFeedback(FeedbackItem $item): void
    {
        $this->assertCanAccessUser($item->student, $item->advisor);
    }
}
