<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\GuidanceRecord;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManuscriptVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        Storage::fake('local');
        $advisor = User::create(['name' => '王导师', 'email' => 'a@e.com', 'password' => 'x', 'role' => 'advisor']);
        $student = User::create(['name' => '张学生', 'email' => 's@e.com', 'password' => 'x', 'role' => 'student']);
        $project = Project::create(['title' => '课题', 'student_id' => $student->id, 'advisor_id' => $advisor->id]);

        return [$advisor, $student, $project];
    }

    private function upload(User $student, Project $project, string $versionName, array $extra = []): TestResponse
    {
        Sanctum::actingAs($student);
        $payload = array_merge([
            'title' => $versionName,
            'note' => "{$versionName} 说明",
            'files' => [UploadedFile::fake()->create("{$versionName}.docx", 20)],
        ], $extra);

        return $this->post("/api/projects/{$project->id}/manuscripts", $payload, [
            'Accept' => 'application/json',
        ]);
    }

    public function test_稿件版本号自增且旧稿全部保留(): void
    {
        [, $student, $project] = $this->world();

        $this->upload($student, $project, 'v1')->assertCreated()->assertJsonPath('version_no', 1);
        $this->upload($student, $project, 'v2')->assertCreated()->assertJsonPath('version_no', 2);
        $this->upload($student, $project, 'v3')->assertCreated()->assertJsonPath('version_no', 3);

        $this->assertSame(3, ManuscriptVersion::where('project_id', $project->id)->count());
        $this->assertDatabaseHas('attachments', ['original_name' => 'v1.docx']);
        $this->assertDatabaseHas('attachments', ['original_name' => 'v3.docx']);
    }

    public function test_指导记录精确绑定到被指导的版本_新稿不改变历史指向(): void
    {
        [$advisor, $student, $project] = $this->world();

        $v1Id = $this->upload($student, $project, 'v1')->json('id');
        $v2Id = $this->upload($student, $project, 'v2')->json('id');

        // 导师约谈后针对 v1 留评语（即使 v2 已存在，记录仍指向 v1）
        Sanctum::actingAs($advisor);
        $this->post("/api/projects/{$project->id}/guidance", [
            'manuscript_version_id' => $v1Id,
            'summary' => '针对 v1 的总体意见',
            'comments' => ['请补充文献综述', '图3不清晰'],
        ], ['Accept' => 'application/json'])->assertCreated();

        $record = GuidanceRecord::first();
        $this->assertSame($v1Id, $record->manuscript_version_id);
        $this->assertSame(2, $record->comments()->count());

        // 学生提交 v3 并逐条回应
        Sanctum::actingAs($student);
        $c1 = Comment::where('content', '请补充文献综述')->first();
        $v3Resp = $this->upload($student, $project, 'v3', [
            'responses' => [
                ['comment_id' => $c1->id, 'content' => '已补充近三年12篇文献。'],
            ],
        ]);
        $v3Resp->assertCreated();
        $v3Id = $v3Resp->json('id');

        // 回应挂在 v3 上，评语变为 resolved；指导记录仍指向 v1（版本可追溯）
        $this->assertSame($v3Id, $c1->responses()->first()->manuscript_version_id);
        $this->assertSame('resolved', $c1->fresh()->status);
        $this->assertSame($v1Id, GuidanceRecord::first()->manuscript_version_id);

        // 课题详情时间线完整呈现
        Sanctum::actingAs($student);
        $timeline = $this->getJson("/api/projects/{$project->id}")->json();
        $this->assertCount(3, $timeline['manuscript_versions']);
        $this->assertSame($v1Id, $timeline['guidance_records'][0]['manuscript_version']['id']);
    }

    public function test_未被引用的误传稿件可以撤回(): void
    {
        [, $student, $project] = $this->world();

        $v1 = $this->upload($student, $project, 'v1')->json('id');

        Sanctum::actingAs($student);
        $this->post("/api/manuscripts/{$v1}/withdraw", ['reason' => '传错文件了'], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame('withdrawn', ManuscriptVersion::find($v1)->status);
        // 撤回不删除行，附件记录仍在
        $this->assertDatabaseHas('manuscript_versions', ['id' => $v1, 'status' => 'withdrawn']);
    }

    public function test_已被指导记录引用的稿件不能撤回(): void
    {
        [$advisor, $student, $project] = $this->world();

        $v1Id = $this->upload($student, $project, 'v1')->json('id');

        Sanctum::actingAs($advisor);
        $this->post("/api/projects/{$project->id}/guidance", [
            'manuscript_version_id' => $v1Id,
            'summary' => '已约谈',
            'comments' => ['一条意见'],
        ], ['Accept' => 'application/json'])->assertCreated();

        Sanctum::actingAs($student);
        $this->post("/api/manuscripts/{$v1Id}/withdraw", ['reason' => '想撤回'], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame('active', ManuscriptVersion::find($v1Id)->status);

        // 指导记录没有编辑/删除入口：遍历路由，GuidanceController 只允许 POST(store)/GET(show)
        $methods = [];
        foreach (app('router')->getRoutes() as $route) {
            $controller = $route->getActionName();
            if (str_contains($controller, 'GuidanceController')) {
                $methods[] = implode(':', $route->methods) . '@' . class_basename(explode('@', $controller)[0] ?? '');
            }
        }
        $joined = implode(',', $methods);
        $this->assertStringContainsString('POST', $joined);
        $this->assertStringNotContainsString('PUT', $joined);
        $this->assertStringNotContainsString('PATCH', $joined);
        $this->assertStringNotContainsString('DELETE', $joined);
    }

    public function test_学生无权关闭评语_只有导师可以(): void
    {
        [$advisor, $student, $project] = $this->world();

        $v1Id = $this->upload($student, $project, 'v1')->json('id');

        Sanctum::actingAs($advisor);
        $record = $this->post("/api/projects/{$project->id}/guidance", [
            'manuscript_version_id' => $v1Id,
            'summary' => '约谈',
            'comments' => ['需要修改'],
        ], ['Accept' => 'application/json'])->json('id');

        $commentId = Comment::where('guidance_record_id', $record)->first()->id;

        // 学生尝试关闭 → 403
        Sanctum::actingAs($student);
        $this->post("/api/comments/{$commentId}/close", ['Accept' => 'application/json'])
            ->assertStatus(403);
        $this->assertSame('open', Comment::find($commentId)->status);

        // 导师关闭成功
        Sanctum::actingAs($advisor);
        $this->post("/api/comments/{$commentId}/close")->assertOk()
            ->assertJsonPath('status', 'closed');
    }

    public function test_导师依据最终稿与未关闭意见确认答辩资格(): void
    {
        [$advisor, $student, $project] = $this->world();

        $v1Id = $this->upload($student, $project, 'v1')->json('id');

        Sanctum::actingAs($advisor);
        $recordId = $this->post("/api/projects/{$project->id}/guidance", [
            'manuscript_version_id' => $v1Id,
            'summary' => '约谈',
            'comments' => ['待修改'],
        ], ['Accept' => 'application/json'])->json('id');

        // 没有最终稿 → 不能确认资格
        $this->post("/api/projects/{$project->id}/eligibility", [
            'defense_eligible' => true,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // 提交最终稿
        Sanctum::actingAs($student);
        $this->upload($student, $project, 'final', ['is_final' => true])->assertCreated();

        // 有未关闭意见 → 拒绝并提示数量
        Sanctum::actingAs($advisor);
        $this->post("/api/projects/{$project->id}/eligibility", [
            'defense_eligible' => true,
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('errors.open_comment_count.0', 1);

        // 学生无权确认资格
        Sanctum::actingAs($student);
        $this->post("/api/projects/{$project->id}/eligibility", [
            'defense_eligible' => true, 'force' => true,
        ], ['Accept' => 'application/json'])->assertStatus(403);

        // 导师知悉未决意见后 force 确认
        Sanctum::actingAs($advisor);
        $this->post("/api/projects/{$project->id}/eligibility", [
            'defense_eligible' => true,
            'force' => true,
            'note' => '剩余问题为格式微调，不影响答辩。',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('project.defense_eligible', true);
    }
}
