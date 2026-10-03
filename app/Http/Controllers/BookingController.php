<?php

namespace App\Http\Controllers;

use App\Models\AdvisorSlot;
use App\Models\Booking;
use App\Models\Project;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings)
    {
    }

    /** 学生选择导师时段并提交讨论问题 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:advisor_slots,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'questions' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = $request->user();
        $slot = AdvisorSlot::findOrFail($data['slot_id']);
        $project = !empty($data['project_id']) ? Project::find($data['project_id']) : null;

        if ($project) {
            $this->authorize('view', $project);
        }

        $booking = $this->bookings->book($slot, $student, $project, $data['questions'] ?? null);

        return response()->json($booking, 201);
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorize('view', $booking);

        return response()->json($booking->load(['slot', 'student:id,name,student_no', 'project', 'guidanceRecord']));
    }

    /** 学生取消预约，时段释放 */
    public function destroy(Request $request, Booking $booking)
    {
        $this->authorize('cancel', $booking);

        $this->bookings->cancelByStudent($booking);

        return response()->json(['message' => '预约已取消，时段已重新开放。']);
    }
}
