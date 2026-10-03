<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\AppointmentService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => LeaveRequest::where('advisor_id', $request->user()->id)
                ->orderByDesc('start_at')
                ->get(),
        ]);
    }

    public function store(Request $request, AppointmentService $service)
    {
        $data = $request->validate([
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $service->createLeave($request->user(), $data);

        return response()->json(['message' => '请假已登记，冲突时段已释放。'], 201);
    }
}
