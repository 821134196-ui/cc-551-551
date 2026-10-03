<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /** 列表：学生看自己的课题；导师看自己指导的课题 */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Project::with(['student:id,name,student_no', 'advisor:id,name,title']);

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } else {
            $query->where('advisor_id', $user->id);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /**
     * 课题详情：版本时间线 + 不可变指导记录，用于追溯每次指导针对哪一版。
     */
    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'student:id,name,student_no',
            'advisor:id,name,title',
            'manuscriptVersions.attachments',
            'manuscriptVersions.responses' => fn ($q) => $q->with('comment:id,guidance_record_id,content,status'),
            'bookings.slot',
            'guidanceRecords.advisor:id,name,title',
            'guidanceRecords.manuscriptVersion:id,version_no,title,status',
            'guidanceRecords.comments.responses.student:id,name',
            'guidanceRecords.attachments',
        ]);

        return response()->json($project);
    }

    /** 学生创建课题并选定导师 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'advisor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'advisor')],
            'deadline_at' => ['nullable', 'date', 'after:now'],
        ]);

        $project = Project::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'student_id' => $request->user()->id,
            'advisor_id' => $data['advisor_id'],
            'deadline_at' => $data['deadline_at'] ?? null,
        ]);

        $advisor = User::find($data['advisor_id']);
        app(\App\Services\NotificationService::class)->send(
            $advisor,
            'project_created',
            '新的毕业设计课题',
            sprintf('%s 选择您作为《%s》的指导导师。', $request->user()->name, $data['title']),
            ['project_id' => $project->id],
        );

        return response()->json($project, 201);
    }

    /** 导师设置提交截止日（导师首页待办排序依据） */
    public function setDeadline(Request $request, Project $project)
    {
        $this->authorize('giveGuidance', $project);

        $data = $request->validate([
            'deadline_at' => ['required', 'date', 'after:now'],
        ]);

        $project->update(['deadline_at' => $data['deadline_at']]);

        app(\App\Services\NotificationService::class)->send(
            $project->student,
            'deadline_set',
            '导师设置了提交截止日',
            sprintf('《%s》提交截止：%s', $project->title, $project->fresh()->deadline_at->toDateTimeString()),
            ['project_id' => $project->id],
        );

        return response()->json($project->fresh());
    }
}
