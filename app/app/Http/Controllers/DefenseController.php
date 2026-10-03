<?php

namespace App\Http\Controllers;

use App\Models\DefenseDecision;
use App\Models\FeedbackItem;
use App\Models\User;
use App\Services\DefenseService;
use Illuminate\Http\Request;

class DefenseController extends Controller
{
    public function show(Request $request, User $student)
    {
        if ($request->user()->isAdvisor()) {
            if ($student->advisor_id !== $request->user()->id) {
                return response()->json(['message' => '无权查看该学生的答辩资格。'], 403);
            }
        } elseif ($student->id !== $request->user()->id) {
            return response()->json(['message' => '无权查看他人的答辩资格。'], 403);
        }

        $decision = DefenseDecision::with('finalSubmission')->where('student_id', $student->id)->first();

        return response()->json([
            'data' => $decision ? [
                'eligible' => $decision->eligible,
                'comment' => $decision->comment,
                'unresolved_count' => $decision->unresolved_count,
                'decided_at' => $decision->decided_at->toIso8601String(),
                'final_version' => $decision->finalSubmission?->version,
            ] : null,
            'current_unresolved_count' => FeedbackItem::where('student_id', $student->id)
                ->where('status', '!=', FeedbackItem::STATUS_CLOSED)->count(),
        ]);
    }

    public function decide(Request $request, User $student, DefenseService $service)
    {
        $data = $request->validate([
            'final_submission_id' => ['required', 'integer', 'exists:submissions,id'],
            'eligible' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // 角色 + 师生关系由 DefenseService 统一校验
        $decision = $service->decide($request->user(), $student, $data);

        return response()->json(['data' => $decision->load('finalSubmission')], 201);
    }
}
