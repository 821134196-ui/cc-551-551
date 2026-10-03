<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Services\ManuscriptService;
use Illuminate\Http\Request;

class ManuscriptController extends Controller
{
    public function __construct(private ManuscriptService $manuscripts)
    {
    }

    /**
     * 学生上传新稿（multipart/form-data）。
     * files[] 附件；responses[comment_id] / responses[content] 为对旧评语的逐条回应。
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('submitManuscript', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'is_final' => ['nullable', 'boolean'],
            'files' => ['required'],
            'files.*' => ['file', 'max:20480'],
            'responses' => ['nullable', 'array'],
            'responses.*.comment_id' => ['required_with:responses', 'integer'],
            'responses.*.content' => ['required_with:responses', 'string', 'max:2000'],
        ]);

        $booking = null;
        if (!empty($data['booking_id'])) {
            $booking = Booking::findOrFail($data['booking_id']);
            if ($booking->student_id !== $request->user()->id) {
                abort(403, '只能关联自己的预约。');
            }
        }

        $version = $this->manuscripts->submitVersion(
            project: $project,
            student: $request->user(),
            title: $data['title'],
            note: $data['note'] ?? null,
            files: $request->file('files', []),
            responses: $data['responses'] ?? [],
            booking: $booking,
            isFinal: (bool) ($data['is_final'] ?? false),
        );

        return response()->json($version->load('attachments'), 201);
    }

    /** 单版本详情：附件、指导记录、所回应的评语 —— 版本追溯核心 */
    public function show(Request $request, ManuscriptVersion $version)
    {
        $this->authorize('view', $version->project);

        $version->load([
            'attachments',
            'student:id,name,student_no',
            'booking.slot',
            'guidanceRecords' => fn ($q) => $q->with(['advisor:id,name,title', 'comments.responses']),
            'responses.comment.guidanceRecord:id,manuscript_version_id',
        ]);

        return response()->json($version);
    }

    /** 学生撤回误传稿件（已被指导记录引用则拒绝） */
    public function withdraw(Request $request, ManuscriptVersion $version)
    {
        $this->authorize('submitManuscript', $version->project);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updated = $this->manuscripts->withdrawVersion($version, $data['reason']);

        return response()->json([
            'version' => $updated,
            'message' => '稿件已撤回（历史行保留）。',
        ]);
    }
}
