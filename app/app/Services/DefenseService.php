<?php

namespace App\Services;

use App\Models\DefenseDecision;
use App\Models\FeedbackItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class DefenseService
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /**
     * 答辩资格只能由导师确认，依据：指定最终稿 + 当前未解决（未关闭）意见数。
     * 学生或其他导师均无权操作。
     */
    public function decide(User $advisor, User $student, array $data): DefenseDecision
    {
        if ($student->role !== User::ROLE_STUDENT || $student->advisor_id !== $advisor->id) {
            throw new HttpResponseException(response()->json([
                'message' => '只能由学生的指导导师确认答辩资格。',
            ], 403));
        }

        $finalSubmission = Submission::findOrFail($data['final_submission_id']);
        if ($finalSubmission->student_id !== $student->id) {
            throw new HttpResponseException(response()->json([
                'message' => '最终稿不属于该学生。',
            ], 422));
        }

        $unresolved = FeedbackItem::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', FeedbackItem::STATUS_CLOSED)
            ->count();

        $decision = DefenseDecision::updateOrCreate(
            ['student_id' => $student->id],
            [
                'advisor_id' => $advisor->id,
                'final_submission_id' => $finalSubmission->id,
                'eligible' => (bool) $data['eligible'],
                'comment' => $data['comment'] ?? null,
                'unresolved_count' => $unresolved,
                'decided_at' => now(),
            ],
        );

        $this->notifier->send(
            $student,
            'defense_decided',
            $data['eligible'] ? '答辩资格已确认' : '答辩资格暂未通过',
            sprintf(
                '导师依据第 %d 版最终稿及 %d 条未解决意见作出判定：%s',
                $finalSubmission->version,
                $unresolved,
                $data['eligible'] ? '具备答辩资格' : '暂不具备答辩资格',
            ),
            ['eligible' => (bool) $data['eligible'], 'unresolved_count' => $unresolved],
        );

        return $decision;
    }
}
