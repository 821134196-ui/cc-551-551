<?php

namespace App\Http\Controllers;

use App\Models\AdvisorSlot;
use App\Services\BookingService;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function __construct(private BookingService $bookings)
    {
    }

    /** 导师创建开放时段（可批量） */
    public function store(Request $request)
    {
        $data = $request->validate([
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ], [], [
            'start_at' => '开始时间',
            'end_at' => '结束时间',
        ]);

        $advisor = $request->user();

        $slot = AdvisorSlot::create([
            'advisor_id' => $advisor->id,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'status' => 'open',
        ]);

        return response()->json($slot, 201);
    }

    /** 学生：浏览导师的可预约时段 */
    public function openSlots(Request $request)
    {
        $request->validate([
            'advisor_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $slots = AdvisorSlot::where('advisor_id', $request->advisor_id)
            ->where('status', 'open')
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->get();

        return response()->json($slots);
    }

    /** 导师：查看自己的时段与预约情况 */
    public function mine(Request $request)
    {
        $slots = AdvisorSlot::with(['booking.student:id,name,student_no', 'booking.project:id,title'])
            ->where('advisor_id', $request->user()->id)
            ->orderBy('start_at')
            ->get();

        return response()->json($slots);
    }

    /** 导师请假：释放该时段，已预约学生被通知改约 */
    public function leave(Request $request, AdvisorSlot $slot)
    {
        $this->authorize('manage', $slot);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $slot = $this->bookings->advisorLeave($slot, $data['reason'] ?? null);

        return response()->json([
            'slot' => $slot->load(['booking.student:id,name,student_no']),
            'message' => '时段已标记为请假并释放。',
        ]);
    }

    /** 导师重新开放一个请假/已结束的空时段 */
    public function reopen(Request $request, AdvisorSlot $slot)
    {
        $this->authorize('manage', $slot);

        if ($slot->booking()->where('status', 'booked')->exists()) {
            abort(422, '该时段仍有有效预约，不能开放。');
        }

        $slot->update(['status' => 'open']);

        return response()->json($slot);
    }
}
