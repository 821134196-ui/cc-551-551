<?php

namespace Tests\Feature;

use App\Models\AdvisorSlot;
use App\Models\Attachment;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\GuidanceRecord;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossAdvisorAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $world;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // 导师 A + 学生甲（课题 P1）；导师 B + 学生乙（课题 P2）
        $wang = User::create(['name' => '王导师', 'email' => 'wang@e.com', 'password' => 'x', 'role' => 'advisor']);
        $li = User::create(['name' => '李导师', 'email' => 'li@e.com', 'password' => 'x', 'role' => 'advisor']);
        $zhang = User::create(['name' => '张学生', 'email' => 'zhang@e.com', 'password' => 'x', 'role' => 'student']);
        $chen = User::create(['name' => '陈学生', 'email' => 'chen@e.com', 'password' => 'x', 'role' => 'student']);

        $p1 = Project::create(['title' => '王-张 课题', 'student_id' => $zhang->id, 'advisor_id' => $wang->id]);
        $p2 = Project::create(['title' => '李-陈 课题', 'student_id' => $chen->id, 'advisor_id' => $li->id]);

        $slot = AdvisorSlot::create(['advisor_id' => $wang->id, 'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(), 'status' => 'open']);
        $booking = Booking::create(['slot_id' => $slot->id, 'student_id' => $zhang->id, 'project_id' => $p1->id, 'status' => 'booked']);
        $slot->update(['status' => 'booked']);

        $version = ManuscriptVersion::create([
            'project_id' => $p1->id, 'student_id' => $zhang->id, 'booking_id' => $booking->id,
            'version_no' => 1, 'title' => 'v1', 'status' => 'active',
        ]);
        $attachment = Attachment::create([
            'attachable_type' => ManuscriptVersion::class, 'attachable_id' => $version->id,
            'path' => 'attachments/manuscripts/1/v1.docx', 'original_name' => 'v1.docx',
            'mime' => 'application/octet-stream', 'size' => 10, 'uploaded_by' => $zhang->id,
        ]);
        // 让物理文件真实存在（fake 磁盘），以便鉴权通过后下载成功
        \Illuminate\Support\Facades\Storage::disk('local')->put($attachment->path, '0123456789');
        $record = GuidanceRecord::create([
            'project_id' => $p1->id, 'booking_id' => $booking->id, 'advisor_id' => $wang->id,
            'manuscript_version_id' => $version->id, 'summary' => '王导师的指导',
        ]);
        $comment = Comment::create([
            'guidance_record_id' => $record->id, 'advisor_id' => $wang->id,
            'content' => '王导师的评语', 'status' => 'open',
        ]);

        $this->world = compact('wang', 'li', 'zhang', 'chen', 'p1', 'p2', 'slot', 'booking', 'version', 'attachment', 'record', 'comment');
    }

    public static function forbiddenEndpoints(): array
    {
        return [
            '课题详情' => ['get', '/api/projects/%d', 'p1'],
            '稿件版本' => ['get', '/api/manuscripts/%d', 'version'],
            '指导记录' => ['get', '/api/projects/%d/guidance/%d', ['p1', 'record']],
        ];
    }

    /**
     * @dataProvider forbiddenEndpoints
     */
    #[DataProvider('forbiddenEndpoints')]
    public function test_其他导师无法查看不属于自己的资料(string $method, string $template, $key): void
    {
        $li = $this->world['li'];
        Sanctum::actingAs($li);

        $ids = $this->resolveIds($key, $template);
        $url = vsprintf($template, $ids);

        $this->{$method . 'Json'}($url)->assertStatus(403);
    }

    public function test_其他导师下载附件被拒(): void
    {
        Sanctum::actingAs($this->world['li']);
        $this->getJson("/api/attachments/{$this->world['attachment']->id}/download")
            ->assertStatus(403);
    }

    public function test_其他导师不能给别人的课题写指导记录(): void
    {
        Sanctum::actingAs($this->world['li']);
        $this->postJson("/api/projects/{$this->world['p1']->id}/guidance", [
            'manuscript_version_id' => $this->world['version']->id,
            'summary' => '越权指导',
            'comments' => ['不该出现'],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('guidance_records', ['summary' => '越权指导']);
    }

    public function test_其他导师不能关闭别人课题的评语(): void
    {
        Sanctum::actingAs($this->world['li']);
        $this->postJson("/api/comments/{$this->world['comment']->id}/close")
            ->assertStatus(403);
        $this->assertSame('open', $this->world['comment']->fresh()->status);
    }

    public function test_其他导师不能确认别人课题的答辩资格(): void
    {
        Sanctum::actingAs($this->world['li']);
        $this->postJson("/api/projects/{$this->world['p1']->id}/eligibility", [
            'defense_eligible' => true, 'force' => true,
        ])->assertStatus(403);
        $this->assertNull($this->world['p1']->fresh()->defense_eligible);
    }

    public function test_其他导师不能对别人的时段请假或查看预约(): void
    {
        Sanctum::actingAs($this->world['li']);

        $this->postJson("/api/slots/{$this->world['slot']->id}/leave")->assertStatus(403);
        $this->assertSame('booked', $this->world['slot']->fresh()->status);

        $this->getJson("/api/bookings/{$this->world['booking']->id}")->assertStatus(403);
    }

    public function test_其他学生不能查看别人的课题与稿件(): void
    {
        Sanctum::actingAs($this->world['chen']);

        $this->getJson("/api/projects/{$this->world['p1']->id}")->assertStatus(403);
        $this->getJson("/api/manuscripts/{$this->world['version']->id}")->assertStatus(403);
        $this->getJson("/api/attachments/{$this->world['attachment']->id}/download")->assertStatus(403);
    }

    public function test_未登录请求被拦截401(): void
    {
        $this->getJson("/api/projects/{$this->world['p1']->id}")->assertStatus(401);
    }

    public function test_课题学生与本人导师可以正常访问(): void
    {
        Sanctum::actingAs($this->world['zhang']);
        $this->getJson("/api/projects/{$this->world['p1']->id}")->assertOk();

        Sanctum::actingAs($this->world['wang']);
        $this->getJson("/api/projects/{$this->world['p1']->id}")->assertOk();
        $this->getJson("/api/attachments/{$this->world['attachment']->id}/download")->assertOk();
    }

    private function resolveIds($key, string $template): array
    {
        // 指导记录需要 project + record 两个 id
        if (str_contains($template, 'guidance')) {
            return [$this->world['p1']->id, $this->world['record']->id];
        }

        return [$this->world[$key]->id];
    }
}
