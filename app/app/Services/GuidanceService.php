<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\FeedbackItem;
use App\Models\GuidanceSession;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class GuidanceService
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /**
     * 导师在约谈后针对指定稿件版本留下评语与逐条修改意见。
     *
     * 指导记录一经创建即不可变：系统不提供编辑/删除接口；
     * 且永久固定引用 submission_id，旧稿撤回也不改变该引用。
     *
     * @param  array<int, string>  $items
     * @param  array<int, UploadedFile>  $files
     */
    public function record(User $advisor, Appointment $appointment, array $data, array $files = []): GuidanceSession
    {
        if ($appointment->advisor_id !== $advisor->id) {
            throw new HttpResponseException(response()->json(['message' => '无权操作此预约。'], 403));
        }

        $submission = Submission::findOrFail($data['submission_id']);

        if ($submission->student_id !== $appointment->student_id
            || $submission->advisor_id !== $advisor->id) {
            throw new HttpResponseException(response()->json([
                'message' => '评语只能针对本次约谈学生提交给你的稿件版本。',
            ], 422));
        }

        if ($submission->status === Submission::STATUS_WITHDRAWN) {
            throw new HttpResponseException(response()->json([
                'message' => '该版本已被学生撤回，不能作为本次指导对象。',
            ], 422));
        }

        if ($appointment->guidanceSession()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => '该预约已有指导记录，记录不可修改；请在下一次约谈中补充。',
            ], 422));
        }

        return DB::transaction(function () use ($advisor, $appointment, $submission, $data, $files) {
            if ($appointment->status === Appointment::STATUS_BOOKED) {
                $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
            }

            $session = GuidanceSession::create([
                'appointment_id' => $appointment->id,
                'submission_id' => $submission->id, // 固定"针对哪一版"
                'advisor_id' => $advisor->id,
                'student_id' => $appointment->student_id,
                'overall_comment' => $data['overall_comment'],
            ]);

            $sequence = 1;
            foreach ($data['feedback_items'] ?? [] as $content) {
                $content = trim((string) $content);
                if ($content === '') {
                    continue;
                }
                FeedbackItem::create([
                    'guidance_session_id' => $session->id,
                    'advisor_id' => $advisor->id,
                    'student_id' => $appointment->student_id,
                    'sequence' => $sequence++,
                    'content' => $content,
                    'status' => FeedbackItem::STATUS_OPEN,
                ]);
            }

            foreach ($files as $file) {
                AttachmentService::store($file, $session, $advisor);
            }

            $this->notifier->send(
                $appointment->student,
                'guidance_recorded',
                '导师已填写第 ' . $submission->version . ' 版指导意见',
                sprintf('针对第 %d 版《%s》有 %d 条修改意见待处理。',
                    $submission->version, $submission->title, $sequence - 1),
                ['guidance_session_id' => $session->id, 'submission_id' => $submission->id],
            );

            return $session->load(['feedbackItems', 'attachments', 'submission', 'appointment.timeSlot']);
        });
    }
}
