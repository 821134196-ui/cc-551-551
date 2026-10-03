<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ManuscriptService;
use Illuminate\Http\Request;

class EligibilityController extends Controller
{
    public function __construct(private ManuscriptService $manuscripts)
    {
    }

    /**
     * 导师根据最终稿与未解决问题确认答辩资格。
     * 学生无权调用（策略 decideEligibility 仅允许课题导师）。
     */
    public function decide(Request $request, Project $project)
    {
        $this->authorize('decideEligibility', $project);

        $data = $request->validate([
            'defense_eligible' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'force' => ['nullable', 'boolean'],
        ]);

        $updated = $this->manuscripts->decideEligibility(
            $project,
            $request->user(),
            (bool) $data['defense_eligible'],
            $data['note'] ?? null,
            (bool) ($data['force'] ?? false),
        );

        return response()->json([
            'project' => $updated->fresh(),
            'open_comment_count' => $this->manuscripts->openCommentCount($updated),
        ]);
    }
}
