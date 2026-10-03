<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Submission;
use App\Services\AttachmentService;
use App\Services\SubmissionService;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    use ChecksOwnership;

    public function index(Request $request)
    {
        $user = $request->user();

        // 导师可带 student_id 查看某名自己学生的版本链；学生只能看自己
        $query = Submission::query()
            ->with(['student', 'attachments', 'previous', 'responses.feedbackItem', 'guidanceSessions'])
            ->orderBy('version');

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } else {
            $studentId = $request->query('student_id');
            if ($studentId) {
                $student = \App\Models\User::findOrFail($studentId);
                if ($student->advisor_id !== $user->id) {
                    return response()->json(['message' => '无权访问该学生资料。'], 403);
                }
                $query->where('student_id', $studentId);
            } else {
                $query->where('advisor_id', $user->id);
            }
        }

        return response()->json(['data' => $query->get()->map(fn (Submission $s) => $this->present($s))]);
    }

    public function store(Request $request, SubmissionService $service)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'files' => ['array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
            'responses' => ['array'],
            'responses.*.feedback_item_id' => ['required', 'integer', 'exists:feedback_items,id'],
            'responses.*.reply' => ['required', 'string', 'max:2000'],
        ]);

        $submission = $service->submit(
            $request->user(),
            $data,
            $request->file('files', []),
            $data['responses'] ?? [],
        );

        return response()->json(['data' => $this->present($submission)], 201);
    }

    public function show(Request $request, Submission $submission)
    {
        $this->assertCanAccessSubmission($submission);

        $submission->load([
            'student', 'attachments', 'previous',
            'responses.feedbackItem',
            'guidanceSessions.feedbackItems.responses',
            'guidanceSessions.attachments',
            'guidanceSessions.appointment.timeSlot',
        ]);

        return response()->json(['data' => $this->present($submission, true)]);
    }

    public function withdraw(Request $request, Submission $submission, SubmissionService $service)
    {
        if ($request->user()->id !== $submission->student_id) {
            return response()->json(['message' => '只有学生本人可以撤回稿件。'], 403);
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json(['data' => $this->present($service->withdraw($submission, $data['reason'] ?? null))]);
    }

    public function download(Request $request, Attachment $attachment, Submission $submission = null)
    {
        // 附件多态：先解析挂载对象再做归属校验
        $attachable = $attachment->attachable;
        if ($attachable instanceof Submission) {
            $this->assertCanAccessSubmission($attachable);
        } elseif ($attachable instanceof \App\Models\GuidanceSession) {
            $this->assertCanAccessSession($attachable);
        } else {
            abort(404);
        }

        return AttachmentService::download($attachment);
    }

    private function present(Submission $s, bool $detail = false): array
    {
        return [
            'id' => $s->id,
            'version' => $s->version,
            'title' => $s->title,
            'note' => $s->note,
            'status' => $s->status,
            'withdraw_reason' => $s->withdraw_reason,
            'withdrawn_at' => $s->withdrawn_at?->toIso8601String(),
            'previous_id' => $s->previous_id,
            'created_at' => $s->created_at->toIso8601String(),
            'student' => $s->relationLoaded('student') ? ['id' => $s->student->id, 'name' => $s->student->name] : null,
            'attachments' => $s->relationLoaded('attachments') ? $s->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'size' => $a->size,
                'download_url' => url("/api/attachments/{$a->id}/download"),
            ]) : [],
            'responses' => $s->relationLoaded('responses') ? $s->responses->map(fn ($r) => [
                'id' => $r->id,
                'reply' => $r->reply,
                'feedback_item_id' => $r->feedback_item_id,
                'feedback_sequence' => $r->relationLoaded('feedbackItem') && $r->feedbackItem
                    ? $r->feedbackItem->sequence : null,
            ]) : [],
            'guidance_sessions' => $detail && $s->relationLoaded('guidanceSessions')
                ? $s->guidanceSessions->map(fn ($g) => [
                    'id' => $g->id,
                    'overall_comment' => $g->overall_comment,
                    'created_at' => $g->created_at->toIso8601String(),
                    'appointment_at' => $g->relationLoaded('appointment') && $g->appointment
                        && $g->appointment->relationLoaded('timeSlot') && $g->appointment->timeSlot
                        ? $g->appointment->timeSlot->start_at->toIso8601String() : null,
                    'feedback_items' => $g->feedbackItems->map(fn ($f) => [
                        'id' => $f->id,
                        'sequence' => $f->sequence,
                        'content' => $f->content,
                        'status' => $f->status,
                        'responses' => $f->relationLoaded('responses') ? $f->responses->map(fn ($r) => [
                            'id' => $r->id,
                            'reply' => $r->reply,
                            'submission_version' => $s->version,
                            'created_at' => $r->created_at->toIso8601String(),
                        ]) : [],
                    ]),
                ])
                : ($s->relationLoaded('guidanceSessions')
                    ? $s->guidanceSessions->map(fn ($g) => ['id' => $g->id]) : []),
        ];
    }
}
