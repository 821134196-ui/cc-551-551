<?php

namespace Tests\Feature;

use App\Models\AdvisorSlot;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\ManuscriptVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_导师首页按预约时间升序返回待办且无歧义列错误(): void
    {
        $advisor = User::create(['name' => '导师', 'email' => 'a@e.com', 'password' => 'x', 'role' => 'advisor']);
        $student = User::create(['name' => '学生', 'email' => 's@e.com', 'password' => 'x', 'role' => 'student']);

        $project = Project::create([
            'title' => '课题', 'student_id' => $student->id, 'advisor_id' => $advisor->id,
            'deadline_at' => now()->addDays(5),
        ]);

        // 两个未来预约，刻意乱序插入，期望返回按开始时间升序
        $later = AdvisorSlot::create(['advisor_id' => $advisor->id, 'start_at' => now()->addDays(3), 'end_at' => now()->addDays(3)->addHour(), 'status' => 'booked']);
        $sooner = AdvisorSlot::create(['advisor_id' => $advisor->id, 'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(), 'status' => 'booked']);
        Booking::create(['slot_id' => $later->id, 'student_id' => $student->id, 'project_id' => $project->id, 'status' => 'booked']);
        Booking::create(['slot_id' => $sooner->id, 'student_id' => $student->id, 'project_id' => $project->id, 'status' => 'booked']);

        Sanctum::actingAs($advisor);
        $res = $this->getJson('/api/dashboard/advisor')->assertOk();

        $bookings = $res->json('upcoming_bookings');
        $this->assertCount(2, $bookings);
        // 更早的时段排前面
        $this->assertSame($sooner->id, $bookings[0]['slot_id']);
        $this->assertSame($later->id, $bookings[1]['slot_id']);
        // 未来截止日天数为正（创建与查询间隔数秒，允许取整为 4 或 5）
        $this->assertGreaterThanOrEqual(4, $res->json('deadlines.0.days_left'));
    }

    public function test_学生首页聚合未关闭意见数(): void
    {
        $advisor = User::create(['name' => '导师', 'email' => 'a@e.com', 'password' => 'x', 'role' => 'advisor']);
        $student = User::create(['name' => '学生', 'email' => 's@e.com', 'password' => 'x', 'role' => 'student']);
        $project = Project::create(['title' => '课题', 'student_id' => $student->id, 'advisor_id' => $advisor->id]);
        $version = ManuscriptVersion::create([
            'project_id' => $project->id, 'student_id' => $student->id,
            'version_no' => 1, 'title' => 'v1', 'status' => 'active',
        ]);

        Sanctum::actingAs($advisor);
        $this->postJson("/api/projects/{$project->id}/guidance", [
            'manuscript_version_id' => $version->id,
            'summary' => '约谈',
            'comments' => ['意见一', '意见二'],
        ])->assertCreated();

        Sanctum::actingAs($student);
        $res = $this->getJson('/api/dashboard/student')->assertOk();
        $this->assertSame(2, $res->json('total_open_comments'));

        // 一名其他导师看不到任何聚合数据（他名下无课题），更无法访问该课题
        $otherAdvisor = User::create(['name' => '别的导师', 'email' => 'b@e.com', 'password' => 'x', 'role' => 'advisor']);
        Sanctum::actingAs($otherAdvisor);
        $this->getJson("/api/projects/{$project->id}")->assertStatus(403);
        $this->assertSame(0, $this->getJson('/api/dashboard/advisor')->json('counts.upcoming_bookings'));
    }
}
