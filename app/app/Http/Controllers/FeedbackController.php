<?php

namespace App\Http\Controllers;

use App\Models\FeedbackItem;
use App\Services\FeedbackService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    use ChecksOwnership;

    public function close(Request $request, FeedbackItem $feedbackItem, FeedbackService $service)
    {
        // ChecksOwnership 先拦截其他导师；service 内再校验必须是意见归属导师
        $this->assertCanAccessFeedback($feedbackItem);

        if ($request->user()->id !== $feedbackItem->advisor_id) {
            // 学生本人也不能关闭意见
            return response()->json(['message' => '学生无权关闭意见，只有指导导师可以关闭。'], 403);
        }

        return response()->json(['data' => $service->close($request->user(), $feedbackItem)]);
    }

    public function reopen(Request $request, FeedbackItem $feedbackItem, FeedbackService $service)
    {
        $this->assertCanAccessFeedback($feedbackItem);

        return response()->json(['data' => $service->reopen($request->user(), $feedbackItem)]);
    }
}
