<?php

namespace App\Http\Controllers;

use App\Models\AdvisorSlot;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Services\ManuscriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private ManuscriptService $manuscripts)
    {
    }

    /**
     * 学生首页：突出“尚未处理（未关闭）的意见”，并聚合课题、稿件、预约。
     */
    public function student(Request $request)
    {
        $student = $request->user();

        $projects = Project::with([
            'advisor:id,name,title',
            'manuscriptVersions' => fn ($q) => $q->orderByDesc('version_no'),
        ])
            ->where('student_id', $student->id)
            ->get();

        $projectCards = $projects->map(function (Project $project) {
            $openComments = Comment::query()
                ->with(['guidanceRecord:id,manuscript_version_id,created_at', 'guidanceRecord.manuscriptVersion:id,version_no,title'])
                ->whereHas('guidanceRecord', fn ($q) => $q->where('project_id', $project->id))
                ->where('status', '!=', Comment::STATUS_CLOSED)
                ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
                ->orderBy('created_at')
                ->get();

            return [
                'id' => $project->id,
                'title' => $project->title,
                'advisor' => $project->advisor ? [
                    'id' => $project->advisor->id,
                    'name' => $project->advisor->name,
                    'title' => $project->advisor->title,
                ] : null,
                'deadline_at' => $project->deadline_at?->toDateTimeString(),
                'defense_eligible' => $project->defense_eligible,
                'latest_version' => $project->manuscriptVersions->first()?->only('id', 'version_no', 'title', 'status', 'is_final', 'created_at'),
                'open_comment_count' => $openComments->count(),
                'open_comments' => $openComments,
            ];
        });

        // 学生的近期预约
        $bookings = Booking::with(['slot', 'project:id,title'])
            ->where('student_id', $student->id)
            ->whereIn('status', ['booked', 'completed'])
            ->whereHas('slot', fn ($q) => $q->where('start_at', '>=', now()->subDays(7)))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'projects' => $projectCards,
            'total_open_comments' => $projectCards->sum('open_comment_count'),
            'upcoming_bookings' => $bookings,
        ]);
    }

    /**
     * 导师首页待办：按预约时间与提交截止日排序。
     */
    public function advisor(Request $request)
    {
        $advisor = $request->user();

        $projects = Project::with('student:id,name,student_no')
            ->where('advisor_id', $advisor->id)
            ->get();

        $projectIds = $projects->pluck('id');

        // 待办 1：即将到来的有效预约（按预约时间升序）
        $upcomingBookings = Booking::with(['slot', 'student:id,name,student_no', 'project:id,title'])
            ->whereIn('bookings.project_id', $projectIds)
            ->where('bookings.status', 'booked')
            ->whereHas('slot', fn ($q) => $q->where('start_at', '>=', now()))
            ->join('advisor_slots', 'bookings.slot_id', '=', 'advisor_slots.id')
            ->orderBy('advisor_slots.start_at')
            ->select('bookings.*')
            ->get();

        // 待办 2：学生已回应、等待导师确认关闭的评语
        $pendingConfirmComments = Comment::with([
            'guidanceRecord:id,project_id,manuscript_version_id',
            'guidanceRecord.project:id,title,student_id',
            'guidanceRecord.project.student:id,name,student_no',
            'responses' => fn ($q) => $q->latest('created_at')->limit(1),
        ])
            ->where('advisor_id', $advisor->id)
            ->where('status', Comment::STATUS_RESOLVED)
            ->orderBy('updated_at')
            ->get();

        // 待办 3：临近截止日（按截止日升序）
        $deadlines = $projects
            ->filter(fn ($p) => $p->deadline_at && $p->deadline_at->isFuture())
            ->sortBy('deadline_at')
            ->map(fn ($p) => [
                'project_id' => $p->id,
                'title' => $p->title,
                'student' => $p->student?->only('id', 'name', 'student_no'),
                'deadline_at' => $p->deadline_at->toDateTimeString(),
                'days_left' => (int) round(now()->diffInDays($p->deadline_at, false)),
            ])
            ->values();

        // 待办 4：等待资格确认（有最终稿但尚未决定）
        $finalPending = $projects->filter(function (Project $p) {
            return $p->defense_eligible === null
                && ManuscriptVersion::where('project_id', $p->id)->where('is_final', true)->where('status', 'active')->exists();
        })->map(fn ($p) => [
            'project_id' => $p->id,
            'title' => $p->title,
            'student' => $p->student?->only('id', 'name', 'student_no'),
            'open_comment_count' => $this->manuscripts->openCommentCount($p),
        ])->values();

        // 今日/本周开放与请假时段概览
        $slots = AdvisorSlot::where('advisor_id', $advisor->id)
            ->where('start_at', '>=', now()->startOfDay())
            ->where('start_at', '<=', now()->addDays(14))
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'upcoming_bookings' => $upcomingBookings,
            'pending_confirm_comments' => $pendingConfirmComments,
            'deadlines' => $deadlines,
            'final_pending' => $finalPending,
            'slots' => $slots,
            'counts' => [
                'upcoming_bookings' => $upcomingBookings->count(),
                'pending_confirm_comments' => $pendingConfirmComments->count(),
                'deadlines' => $deadlines->count(),
                'final_pending' => $finalPending->count(),
            ],
        ]);
    }
}
