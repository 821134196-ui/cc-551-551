<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Project;
use App\Services\ManuscriptService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private ManuscriptService $manuscripts)
    {
    }

    /** 导师确认关闭评语（学生调用将被策略拒绝 403） */
    public function close(Request $request, Comment $comment)
    {
        $this->authorize('manage', $comment);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $comment = $this->manuscripts->closeComment($comment, $request->user(), $data['note'] ?? null);

        return response()->json($comment);
    }

    /** 导师认为修改不到位，重新置为待处理 */
    public function reopen(Request $request, Comment $comment)
    {
        $this->authorize('manage', $comment);

        $comment = $this->manuscripts->reopenComment($comment, $request->user());

        return response()->json($comment);
    }
}
