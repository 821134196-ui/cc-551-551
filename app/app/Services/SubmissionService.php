<?php

namespace App\Services;

use App\Models\FeedbackItem;
use App\Models\FeedbackResponse;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubmissionService
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /**
     * 学生提交新版本稿件，并随稿逐条回应旧意见。
     *
     * @param  array<int, array{feedback_item_id: int, reply: string}>  $responses
     * @param  array<int, UploadedFile>  $files
     */
    public function submit(User $student, array $attributes, array $files = [], array $responses = []): Submission
    {
        if (! $student->advisor_id) {
            throw new HttpResponseException(response()->json([
                'message' => '尚未分配导师，无法提交稿件。',
            ], 422));
        }

        // 校验回应对象：必须是该学生本人、且尚未关闭的意见
        $feedbackIds = array_column($responses, 'feedback_item_id');
        if ($feedbackIds) {
            $ownedCount = FeedbackItem::query()
                ->where('student_id', $student->id)
                ->where('status', '!=', FeedbackItem::STATUS_CLOSED)
                ->whereIn('id', $feedbackIds)
                ->distinct()
                ->count('id');
            if ($ownedCount !== count(array_unique($feedbackIds))) {
                throw new HttpResponseException(response()->json([
                    'message' => '存在不属于你或已关闭的意见，无法回应。',
                ], 403));
            }
        }

        return DB::transaction(function () use ($student, $attributes, $files, $responses) {
            $latest = Submission::query()
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->max('version');

            $submission = Submission::create([
                'student_id' => $student->id,
                'advisor_id' => $student->advisor_id,
                'version' => ($latest ?? 0) + 1,
                'title' => $attributes['title'],
                'note' => $attributes['note'] ?? null,
                'status' => Submission::STATUS_SUBMITTED,
                'previous_id' => $latest
                    ? Submission::where('student_id', $student->id)->where('version', $latest)->value('id')
                    : null,
            ]);

            foreach ($files as $file) {
                AttachmentService::store($file, $submission, $student);
            }

            foreach ($responses as $response) {
                $item = FeedbackItem::findOrFail($response['feedback_item_id']);

                FeedbackResponse::create([
                    'feedback_item_id' => $item->id,
                    'submission_id' => $submission->id,
                    'student_id' => $student->id,
                    'reply' => $response['reply'],
                ]);

                // 学生回应后进入 answered，由导师决定是否关闭（学生无权关闭）
                $item->update(['status' => FeedbackItem::STATUS_ANSWERED]);
            }

            $this->notifier->send(
                $student->advisor,
                'submission_created',
                '收到新稿件版本',
                sprintf('%s 提交了第 %d 版《%s》。', $student->name, $submission->version, $submission->title),
                ['submission_id' => $submission->id, 'version' => $submission->version],
            );

            return $submission->load(['attachments', 'responses', 'student']);
        });
    }

    /**
     * 学生撤回误传稿件。
     * 文件、附件与所有引用它的指导记录全部保留，指导记录不会因此被改动。
     */
    public function withdraw(Submission $submission, ?string $reason): Submission
    {
        if ($submission->status === Submission::STATUS_WITHDRAWN) {
            throw new HttpResponseException(response()->json([
                'message' => '该稿件版本已经撤回。',
            ], 422));
        }

        $submission->update([
            'status' => Submission::STATUS_WITHDRAWN,
            'withdraw_reason' => $reason,
            'withdrawn_at' => now(),
        ]);

        $this->notifier->send(
            $submission->advisor,
            'submission_withdrawn',
            '学生撤回了稿件版本',
            sprintf('%s 撤回了第 %d 版《%s》，相关指导记录仍保留。',
                $submission->student->name, $submission->version, $submission->title),
            ['submission_id' => $submission->id],
        );

        return $submission;
    }
}
