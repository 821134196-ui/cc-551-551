<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Services\ManuscriptService;
use Illuminate\Http\Request;

class GuidanceController extends Controller
{
    public function __construct(private ManuscriptService $manuscripts)
    {
    }

    /**
     * 导师约谈后针对某一稿件版本留下指导记录（含评语、附件）。
     * 无 update/destroy 路由 —— 指导记录创建后不可修改。
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('giveGuidance', $project);

        $data = $request->validate([
            'manuscript_version_id' => ['required', 'integer', 'exists:manuscript_versions,id'],
            'summary' => ['required', 'string', 'max:3000'],
            'comments' => ['required', 'array', 'min:1'],
            'comments.*' => ['required', 'string', 'max:2000'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $version = ManuscriptVersion::findOrFail($data['manuscript_version_id']);

        $booking = null;
        if (!empty($data['booking_id'])) {
            $booking = Booking::findOrFail($data['booking_id']);
            if ($booking->slot->advisor_id !== $request->user()->id) {
                abort(403, '只能关联自己名下的预约。');
            }
        }

        $record = $this->manuscripts->createGuidance(
            project: $project,
            advisor: $request->user(),
            version: $version,
            summary: $data['summary'],
            commentTexts: $data['comments'],
            files: $request->file('files', []),
            booking: $booking,
        );

        return response()->json($record, 201);
    }

    public function show(Request $request, Project $project, int $recordId)
    {
        $this->authorize('view', $project);

        $record = $project->guidanceRecords()
            ->with(['advisor:id,name,title', 'manuscriptVersion:id,version_no,title,status', 'comments.responses.student:id,name', 'attachments', 'booking.slot'])
            ->findOrFail($recordId);

        return response()->json($record);
    }
}
