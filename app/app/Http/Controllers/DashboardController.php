<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\DefenseDecision;
use App\Models\FeedbackItem;
use App\Models\Submission;
use App\Models\TimeSlot;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * 学生首页：突出尚未处理（open/answered）的意见，附版本出处；
     * 导师首页：按预约时间、提交截止日排序的待办。
     */
    public function index(Request $request)
    {
        return $request->user()->isStudent()
            ? $this->studentDashboard($request)
            : $this->advisorDashboard($request);
    }

    private function studentDashboard(Request $request)
    {
        $student = $request->user();

        $pendingFeedback = FeedbackItem::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', FeedbackItem::STATUS_CLOSED)
            ->with(['guidanceSession.submission', 'responses.submission', 'advisor'])
            ->get()
            ->sortBy([
                fn ($a, $b) => [$a->status === FeedbackItem::STATUS_OPEN ? 0 : 1],
            ])
            ->values();

        $upcomingAppointments = Appointment::query()
            ->where('student_id', $student->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->with('timeSlot')
            ->get()
            ->sortBy(fn (Appointment $a) => $a->timeSlot->start_at);

        $latestVersion = Submission::where('student_id', $student->id)->max('version') ?? 0;
        $decision = DefenseDecision::where('student_id', $student->id)->latest('decided_at')->first();

        return response()->json([
            'role' => 'student',
            'pending_feedback_count' => $pendingFeedback->count(),
            'pending_feedback' => $pendingFeedback->map(fn (FeedbackItem $f) => [
                'id' => $f->id,
                'sequence' => $f->sequence,
                'content' => $f->content,
                'status' => $f->status,
                'status_label' => $f->status === FeedbackItem::STATUS_OPEN ? '待处理' : '已回应·待导师确认',
                'advisor_name' => $f->advisor->name,
                'submission_version' => $f->guidanceSession->submission?->version,
                'submission_title' => $f->guidanceSession->submission?->title,
                'guidance_session_id' => $f->guidance_session_id,
                'my_responses' => $f->responses->map(fn ($r) => [
                    'reply' => $r->reply,
                    'submission_version' => $r->submission?->version,
                ]),
            ]),
            'upcoming_appointments' => $upcomingAppointments->map(fn (Appointment $a) => [
                'id' => $a->id,
                'start_at' => $a->timeSlot->start_at->toIso8601String(),
                'location' => $a->timeSlot->location,
                'topic' => $a->topic,
                'submit_deadline' => $a->submit_deadline?->toIso8601String(),
            ]),
            'latest_version' => $latestVersion,
            'defense' => $decision ? [
                'eligible' => $decision->eligible,
                'decided_at' => $decision->decided_at->toIso8601String(),
            ] : null,
        ]);
    }

    private function advisorDashboard(Request $request)
    {
        $advisor = $request->user();

        // 待办①：未来预约，按预约时间升序
        $upcoming = Appointment::query()
            ->where('advisor_id', $advisor->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->with(['timeSlot', 'student'])
            ->get()
            ->map(function (Appointment $a) {
                return [
                    'kind' => 'appointment',
                    'appointment_id' => $a->id,
                    'student_name' => $a->student->name,
                    'start_at' => $a->timeSlot->start_at->toIso8601String(),
                    'sort_at' => $a->timeSlot->start_at->toIso8601String(),
                    'submit_deadline' => $a->submit_deadline?->toIso8601String(),
                    'topic' => $a->topic,
                    'has_guidance' => false,
                ];
            });

        // 待办②：已结束但还没写评语的约谈
        $pendingGuidance = Appointment::query()
            ->where('advisor_id', $advisor->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->whereHas('timeSlot', fn ($q) => $q->where('start_at', '<=', now()))
            ->with(['timeSlot', 'student'])
            ->get()
            ->map(fn (Appointment $a) => [
                'kind' => 'guidance_due',
                'appointment_id' => $a->id,
                'student_name' => $a->student->name,
                'start_at' => $a->timeSlot->start_at->toIso8601String(),
                'sort_at' => $a->timeSlot->start_at->toIso8601String(),
                'submit_deadline' => $a->submit_deadline?->toIso8601String(),
                'topic' => $a->topic,
                'has_guidance' => false,
            ]);

        // 待办③：临近/已过提交截止日
        $deadlines = Appointment::query()
            ->where('advisor_id', $advisor->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->whereNotNull('submit_deadline')
            ->with(['timeSlot', 'student'])
            ->get()
            ->map(fn (Appointment $a) => [
                'kind' => $a->submit_deadline->isPast() ? 'deadline_overdue' : 'deadline',
                'appointment_id' => $a->id,
                'student_name' => $a->student->name,
                'start_at' => $a->timeSlot->start_at->toIso8601String(),
                'sort_at' => $a->submit_deadline->toIso8601String(),
                'submit_deadline' => $a->submit_deadline->toIso8601String(),
                'topic' => $a->topic,
                'has_guidance' => false,
            ]);

        // 待办④：学生已回应、等导师确认关闭的意见
        $answeredFeedback = FeedbackItem::query()
            ->where('advisor_id', $advisor->id)
            ->where('status', FeedbackItem::STATUS_ANSWERED)
            ->with(['student', 'responses.submission', 'guidanceSession.submission'])
            ->get()
            ->map(fn (FeedbackItem $f) => [
                'kind' => 'feedback_to_review',
                'feedback_item_id' => $f->id,
                'student_name' => $f->student->name,
                'sequence' => $f->sequence,
                'content' => $f->content,
                'answered_in_version' => $f->responses->last()?->submission?->version,
                'reply' => $f->responses->last()?->reply,
                'sort_at' => $f->updated_at->toIso8601String(),
            ]);

        $todos = $upcoming->concat($pendingGuidance)->concat($deadlines)
            ->concat($answeredFeedback)
            ->sortBy('sort_at')
            ->values();

        return response()->json([
            'role' => 'advisor',
            'todo_count' => $todos->count(),
            'todos' => $todos,
            'open_slot_count' => TimeSlot::where('advisor_id', $advisor->id)
                ->where('status', TimeSlot::STATUS_OPEN)
                ->where('start_at', '>', now())
                ->count(),
            'student_count' => $advisor->students()->count(),
        ]);
    }
}
