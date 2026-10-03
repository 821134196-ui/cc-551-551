<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\FeedbackItem;
use App\Models\GuidanceSession;
use App\Models\Submission;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\GuidanceService;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $advisorA;
    private User $advisorB;
    private User $student;
    private User $otherStudent;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->advisorA = User::create([
            'name' => '导师A', 'email' => 'a@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADVISOR, 'api_token' => 'tok-a',
        ]);
        $this->advisorB = User::create([
            'name' => '导师B', 'email' => 'b@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADVISOR, 'api_token' => 'tok-b',
        ]);
        $this->student = User::create([
            'name' => '学生S', 'email' => 's@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_STUDENT, 'advisor_id' => $this->advisorA->id, 'api_token' => 'tok-s',
        ]);
        $this->otherStudent = User::create([
            'name' => '学生T', 'email' => 't@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_STUDENT, 'advisor_id' => $this->advisorB->id, 'api_token' => 'tok-t',
        ]);
        $this->slot = TimeSlot::create([
            'advisor_id' => $this->advisorA->id,
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(),
            'location' => '302', 'status' => TimeSlot::STATUS_OPEN,
        ]);
    }

    // ===== 场景一：同一时段只能由一名学生预约 =====

    public function test_同一时段首次预约成功并锁定时段(): void
    {
        $resp = $this->withToken('tok-s')->postJson('/api/appointments', [
            'time_slot_id' => $this->slot->id,
            'topic' => '开题讨论',
            'questions' => ['问题一', '问题二'],
        ]);
        $resp->assertCreated()->assertJsonPath('data.status', 'booked');

        $this->assertSame(TimeSlot::STATUS_BOOKED, $this->slot->fresh()->status);
        $this->assertDatabaseHas('discussion_questions', ['content' => '问题一']);
    }

    public function test_同一时段第二名学生预约冲突返回409(): void
    {
        $this->withToken('tok-s')->postJson('/api/appointments', ['time_slot_id' => $this->slot->id])
            ->assertCreated();

        // 另一名导师 A 的学生（保证通过"只能预约自己导师"校验，纯粹测试时段冲突）
        $student2 = User::create([
            'name' => '学生S2', 'email' => 's2@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_STUDENT, 'advisor_id' => $this->advisorA->id, 'api_token' => 'tok-s2',
        ]);

        $this->withToken('tok-s2')->postJson('/api/appointments', ['time_slot_id' => $this->slot->id])
            ->assertStatus(409)
            ->assertJsonPath('message', '该时段已被其他学生预约，请选择其他时段。');

        $this->assertSame(1, Appointment::where('time_slot_id', $this->slot->id)
            ->whereIn('status', ['booked', 'completed'])->count());
    }

    public function test_学生不能预约其他导师的时段(): void
    {
        $this->withToken('tok-s')->postJson('/api/appointments', [
            'time_slot_id' => TimeSlot::create([
                'advisor_id' => $this->advisorB->id,
                'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(),
            ])->id,
        ])->assertStatus(403);
    }

    public function test_学生取消后时段释放可被重新预约(): void
    {
        $first = $this->withToken('tok-s')->postJson('/api/appointments', ['time_slot_id' => $this->slot->id])
            ->assertCreated()->json('data.id');

        $this->withToken('tok-s')->postJson("/api/appointments/{$first}/cancel")->assertOk();
        $this->assertSame(TimeSlot::STATUS_OPEN, $this->slot->fresh()->status);

        $student2 = User::create([
            'name' => 'S2', 'email' => 's2@edu', 'password' => Hash::make('x'),
            'role' => User::ROLE_STUDENT, 'advisor_id' => $this->advisorA->id, 'api_token' => 'tok-s2',
        ]);
        $this->withToken('tok-s2')->postJson('/api/appointments', ['time_slot' => $this->slot->id, 'time_slot_id' => $this->slot->id])
            ->assertCreated();
    }

    public function test_导师请假释放已预约时段并通知学生(): void
    {
        $this->withToken('tok-s')->postJson('/api/appointments', ['time_slot_id' => $this->slot->id])
            ->assertCreated();

        $this->withToken('tok-a')->postJson('/api/leaves', [
            'start_at' => now()->addDay()->subHour()->toIso8601String(),
            'end_at' => now()->addDay()->addHours(2)->toIso8601String(),
            'reason' => '外出开会',
        ])->assertCreated();

        $this->assertSame(TimeSlot::STATUS_CANCELLED, $this->slot->fresh()->status);
        $this->assertDatabaseHas('appointments', [
            'time_slot_id' => $this->slot->id,
            'status' => Appointment::STATUS_RELEASED_BY_LEAVE,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'type' => 'slot_released_by_leave',
        ]);
    }

    // ===== 场景二：稿件与评语的版本追溯 =====

    public function test_评语永久固定指向被点评的稿件版本(): void
    {
        $appointment = Appointment::create([
            'time_slot_id' => $this->slot->id,
            'student_id' => $this->student->id,
            'advisor_id' => $this->advisorA->id,
            'status' => Appointment::STATUS_BOOKED,
        ]);

        $v1 = $this->makeSubmission(1, '开题报告 v1');

        $session = app(GuidanceService::class)->record($this->advisorA, $appointment, [
            'submission_id' => $v1->id,
            'overall_comment' => '需要补充实验',
            'feedback_items' => ['补充对照实验', '补充近三年文献'],
        ]);

        // 即使学生后来撤回第 1 版，指导记录的引用与内容不变
        app(SubmissionService::class)->withdraw($v1, '误传');
        $this->assertSame(Submission::STATUS_WITHDRAWN, $v1->fresh()->status);

        $frozen = GuidanceSession::with('submission', 'feedbackItems')->find($session->id);
        $this->assertSame($v1->id, $frozen->submission_id);
        $this->assertSame(1, $frozen->submission->version);
        $this->assertSame('需要补充实验', $frozen->overall_comment);
        $this->assertCount(2, $frozen->feedbackItems);
        $this->assertDatabaseHas('attachments', ['attachable_id' => $v1->id]);

        // 撤回版本不能再作为新评语对象
        $appointment2 = Appointment::create([
            'time_slot_id' => TimeSlot::create([
                'advisor_id' => $this->advisorA->id,
                'start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addHour(),
            ])->id,
            'student_id' => $this->student->id, 'advisor_id' => $this->advisorA->id,
        ]);
        $this->withToken('tok-a')->postJson("/api/appointments/{$appointment2->id}/guidance", [
            'submission_id' => $v1->id,
            'overall_comment' => 'x',
            'feedback_items' => ['y'],
        ])->assertStatus(422);
    }

    public function test_新稿逐条回应旧意见形成版本链(): void
    {
        $appointment = Appointment::create([
            'time_slot_id' => $this->slot->id,
            'student_id' => $this->student->id, 'advisor_id' => $this->advisorA->id,
        ]);
        $v1 = $this->makeSubmission(1, 'v1');
        $session = app(GuidanceService::class)->record($this->advisorA, $appointment, [
            'submission_id' => $v1->id,
            'overall_comment' => 'ok',
            'feedback_items' => ['修改问题A'],
        ]);
        $item = $session->feedbackItems->first();

        // 学生提交 v2 并随稿回应
        $this->withToken('tok-s')->post('/api/submissions', [
            'title' => 'v2',
            'responses' => [['feedback_item_id' => $item->id, 'reply' => '已在 3.1 节修改']],
        ])->assertCreated();

        $this->assertSame(FeedbackItem::STATUS_ANSWERED, $item->fresh()->status);
        $this->assertDatabaseHas('feedback_responses', [
            'feedback_item_id' => $item->id,
            'submission_id' => Submission::where('version', 2)->value('id'),
            'reply' => '已在 3.1 节修改',
        ]);

        // 学生无权关闭意见
        $this->withToken('tok-s')->postJson("/api/feedback/{$item->id}/close")
            ->assertStatus(403);
        $this->assertSame(FeedbackItem::STATUS_ANSWERED, $item->fresh()->status);

        // 导师确认后关闭
        $this->withToken('tok-a')->postJson("/api/feedback/{$item->id}/close")->assertOk();
        $this->assertSame(FeedbackItem::STATUS_CLOSED, $item->fresh()->status);
    }

    public function test_指导记录不可重复填写也不提供修改接口(): void
    {
        $appointment = Appointment::create([
            'time_slot_id' => $this->slot->id,
            'student_id' => $this->student->id, 'advisor_id' => $this->advisorA->id,
        ]);
        $v1 = $this->makeSubmission(1, 'v1');
        $payload = ['submission_id' => $v1->id, 'overall_comment' => '第一次', 'feedback_items' => ['意见1']];

        $this->withToken('tok-a')->postJson("/api/appointments/{$appointment->id}/guidance", $payload)
            ->assertCreated();
        $this->withToken('tok-a')->postJson("/api/appointments/{$appointment->id}/guidance", $payload)
            ->assertStatus(422);

        $this->assertSame(1, GuidanceSession::where('appointment_id', $appointment->id)->count());
    }

    // ===== 场景三：跨导师访问隔离 =====

    public function test_其他导师无法查看学生稿件与评语(): void
    {
        $submission = $this->makeSubmission(1, '保密稿');
        $appointment = Appointment::create([
            'time_slot_id' => $this->slot->id,
            'student_id' => $this->student->id, 'advisor_id' => $this->advisorA->id,
        ]);
        $session = app(GuidanceService::class)->record($this->advisorA, $appointment, [
            'submission_id' => $submission->id,
            'overall_comment' => '保密评语',
            'feedback_items' => ['保密意见'],
        ]);

        // 导师 B 直接访问学生的稿件 → 403
        $this->withToken('tok-b')->getJson("/api/submissions/{$submission->id}")
            ->assertStatus(403);
        // 导师 B 直接访问指导记录 → 403
        $this->withToken('tok-b')->getJson("/api/guidance/{$session->id}")
            ->assertStatus(403);
        // 导师 B 尝试按 student_id 列出导师 A 学生的版本列表 → 403
        $this->withToken('tok-b')->getJson("/api/submissions?student_id={$this->student->id}")
            ->assertStatus(403);
        // 导师 B 下载学生稿件附件 → 403
        $attachment = $submission->attachments->first();
        $this->withToken('tok-b')->get("/api/attachments/{$attachment->id}/download")
            ->assertStatus(403);
    }

    public function test_其他导师无法写评语或确认答辩资格(): void
    {
        $submission = $this->makeSubmission(1, 'v1');

        $this->withToken('tok-b')->postJson("/api/appointments/{$this->anyAppointment()->id}/guidance", [
            'submission_id' => $submission->id, 'overall_comment' => 'x', 'feedback_items' => ['y'],
        ])->assertStatus(403);

        $this->withToken('tok-b')->postJson("/api/students/{$this->student->id}/defense", [
            'final_submission_id' => $submission->id, 'eligible' => true,
        ])->assertStatus(403);
    }

    public function test_答辩资格只能导师依据最终稿与未解决意见确认(): void
    {
        $submission = $this->makeSubmission(1, '最终稿');
        $appointment = Appointment::create([
            'time_slot_id' => $this->slot->id,
            'student_id' => $this->student->id, 'advisor_id' => $this->advisorA->id,
        ]);
        app(GuidanceService::class)->record($this->advisorA, $appointment, [
            'submission_id' => $submission->id, 'overall_comment' => '仍有问题',
            'feedback_items' => ['未解决问题1'],
        ]);

        // 学生不能自己确认
        $this->withToken('tok-s')->postJson("/api/students/{$this->student->id}/defense", [
            'final_submission_id' => $submission->id, 'eligible' => true,
        ])->assertStatus(403);

        // 导师确认：系统自动带入未解决意见数
        $this->withToken('tok-a')->postJson("/api/students/{$this->student->id}/defense", [
            'final_submission_id' => $submission->id, 'eligible' => true, 'comment' => '允许答辩',
        ])->assertCreated()
          ->assertJsonPath('data.unresolved_count', 1)
          ->assertJsonPath('data.final_submission_id', $submission->id);
    }

    private function makeSubmission(int $version, string $title): Submission
    {
        $submission = Submission::create([
            'student_id' => $this->student->id,
            'advisor_id' => $this->advisorA->id,
            'version' => $version,
            'title' => $title,
            'status' => Submission::STATUS_SUBMITTED,
        ]);

        // 本地保存一个真实附件，验证撤回后文件与附件记录仍保留
        $path = 'submissions/' . $submission->id . '/' . Str::random(8) . '.txt';
        \Illuminate\Support\Facades\Storage::disk('attachments')->put($path, '稿件内容 ' . $version);
        $submission->attachments()->create([
            'uploaded_by' => $this->student->id,
            'original_name' => "稿件_v{$version}.txt",
            'path' => $path,
            'mime' => 'text/plain',
            'size' => 20,
        ]);

        return $submission->fresh('attachments');
    }

    private function anyAppointment(): Appointment
    {
        return Appointment::firstOrCreate([
            'time_slot_id' => $this->slot->id,
        ], [
            'student_id' => $this->student->id,
            'advisor_id' => $this->advisorA->id,
            'status' => Appointment::STATUS_BOOKED,
        ]);
    }
}
