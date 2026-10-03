<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TimeSlotController extends Controller
{
    use ChecksOwnership;

    /** 导师：列出自己的时段；学生：列出自己导师的时段 */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdvisor()) {
            $slots = TimeSlot::where('advisor_id', $user->id)
                ->with(['activeAppointment.student'])
                ->orderBy('start_at')
                ->get();
        } else {
            $slots = TimeSlot::where('advisor_id', $user->advisor_id)
                ->with(['activeAppointment.student'])
                ->orderBy('start_at')
                ->get();
        }

        return response()->json(['data' => $slots->map(fn (TimeSlot $s) => $this->present($s))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $slot = TimeSlot::create([
            'advisor_id' => $request->user()->id,
            'start_at' => $data['start_at'],
            'end_at' => Carbon::parse($data['start_at'])->addMinutes($data['duration_minutes']),
            'location' => $data['location'] ?? '线上会议室',
            'status' => TimeSlot::STATUS_OPEN,
        ]);

        return response()->json(['data' => $this->present($slot)], 201);
    }

    public function show(Request $request, TimeSlot $timeSlot)
    {
        $this->assertCanAccessSlot($timeSlot);

        return response()->json(['data' => $this->present($timeSlot->load('activeAppointment.student'))]);
    }

    private function present(TimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'advisor_id' => $slot->advisor_id,
            'start_at' => $slot->start_at->toIso8601String(),
            'end_at' => $slot->end_at->toIso8601String(),
            'location' => $slot->location,
            'status' => $slot->status,
            'cancel_reason' => $slot->cancel_reason,
            'booked_by' => $slot->activeAppointment
                ? ['id' => $slot->activeAppointment->student->id, 'name' => $slot->activeAppointment->student->name]
                : null,
        ];
    }
}
