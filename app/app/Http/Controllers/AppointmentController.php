<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    use ChecksOwnership;

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Appointment::query()
            ->with(['timeSlot', 'student', 'questions', 'guidanceSession.submission']);

        if ($user->isAdvisor()) {
            $query->where('advisor_id', $user->id);
        } else {
            $query->where('student_id', $user->id);
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->get()->map(fn (Appointment $a) => $this->present($a)),
        ]);
    }

    public function store(Request $request, AppointmentService $service)
    {
        $data = $request->validate([
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'topic' => ['nullable', 'string', 'max:200'],
            'questions' => ['array'],
            'questions.*' => ['string', 'max:1000'],
        ]);

        $appointment = $service->book(
            $request->user(),
            $data['time_slot_id'],
            $data['topic'] ?? null,
            $data['questions'] ?? [],
        );

        return response()->json(['data' => $this->present($appointment)], 201);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->assertCanAccessAppointment($appointment);
        $appointment->load([
            'timeSlot', 'student', 'advisor', 'questions',
            'guidanceSession.submission', 'guidanceSession.feedbackItems.responses',
        ]);

        return response()->json(['data' => $this->present($appointment)]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentService $service)
    {
        if ($request->user()->id !== $appointment->student_id) {
            return response()->json(['message' => '只有预约学生本人可以取消。'], 403);
        }

        return response()->json(['data' => $this->present($service->cancelByStudent($appointment))]);
    }

    public function setDeadline(Request $request, Appointment $appointment)
    {
        if ($request->user()->id !== $appointment->advisor_id) {
            return response()->json(['message' => '只有导师可以设置提交截止日。'], 403);
        }

        $data = $request->validate(['submit_deadline' => ['required', 'date', 'after:now']]);
        $appointment->update(['submit_deadline' => $data['submit_deadline']]);

        return response()->json(['data' => $this->present($appointment->load('timeSlot'))]);
    }

    private function present(Appointment $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'topic' => $a->topic,
            'submit_deadline' => $a->submit_deadline?->toIso8601String(),
            'created_at' => $a->created_at->toIso8601String(),
            'time_slot' => $a->relationLoaded('timeSlot') ? [
                'id' => $a->timeSlot->id,
                'start_at' => $a->timeSlot->start_at->toIso8601String(),
                'end_at' => $a->timeSlot->end_at->toIso8601String(),
                'location' => $a->timeSlot->location,
            ] : null,
            'student' => $a->relationLoaded('student') ? [
                'id' => $a->student->id, 'name' => $a->student->name,
            ] : null,
            'advisor' => $a->relationLoaded('advisor') ? [
                'id' => $a->advisor->id, 'name' => $a->advisor->name,
            ] : null,
            'questions' => $a->relationLoaded('questions') ? $a->questions->pluck('content') : [],
            'guidance' => $a->relationLoaded('guidanceSession') && $a->guidanceSession ? [
                'id' => $a->guidanceSession->id,
                'submission_id' => $a->guidanceSession->submission_id,
                'submission_version' => $a->guidanceSession->relationLoaded('submission') && $a->guidanceSession->submission
                    ? $a->guidanceSession->submission->version : null,
            ] : null,
        ];
    }
}
