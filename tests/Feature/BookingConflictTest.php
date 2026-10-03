<?php

namespace Tests\Feature;

use App\Models\AdvisorSlot;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorld(): array
    {
        $advisor = User::create(['name' => '王导师', 'email' => 'a@e.com', 'password' => 'x', 'role' => 'advisor']);
        $s1 = User::create(['name' => '学生甲', 'email' => 's1@e.com', 'password' => 'x', 'role' => 'student']);
        $s2 = User::create(['name' => '学生乙', 'email' => 's2@e.com', 'password' => 'x', 'role' => 'student']);
        $project = Project::create(['title' => '课题', 'student_id' => $s1->id, 'advisor_id' => $advisor->id]);
        $slot = AdvisorSlot::create([
            'advisor_id' => $advisor->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'open',
        ]);

        return [$advisor, $s1, $s2, $project, $slot];
    }

    public function test_同一时段只能由一名学生预约_第二名得到409(): void
    {
        [, $s1, $s2, $project, $slot] = $this->makeWorld();

        Sanctum::actingAs($s1);
        $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'project_id' => $project->id, 'questions' => '问题一'])
            ->assertCreated()
            ->assertJsonPath('status', 'booked');

        // 时段状态变为 booked
        $this->assertSame('booked', $slot->fresh()->status);

        // 学生乙抢同一时段 → 409
        Sanctum::actingAs($s2);
        $this->postJson('/api/bookings', ['slot_id' => $slot->id])
            ->assertStatus(409);

        // 数据库中该时段只有一条有效预约
        $this->assertSame(1, \App\Models\Booking::where('slot_id', $slot->id)->where('status', 'booked')->count());
    }

    public function test_学生取消后时段释放_其他学生可再约(): void
    {
        [, $s1, $s2, $project, $slot] = $this->makeWorld();

        Sanctum::actingAs($s1);
        $bookingId = $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'project_id' => $project->id])
            ->assertCreated()->json('id');

        Sanctum::actingAs($s1);
        $this->deleteJson("/api/bookings/{$bookingId}")->assertOk();

        $this->assertSame('open', $slot->fresh()->status);

        Sanctum::actingAs($s2);
        $this->postJson('/api/bookings', ['slot_id' => $slot->id])
            ->assertCreated()
            ->assertJsonPath('status', 'booked');
    }

    public function test_导师请假后释放时段并通知已预约学生(): void
    {
        [$advisor, $s1, , $project, $slot] = $this->makeWorld();

        Sanctum::actingAs($s1);
        $bookingId = $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'project_id' => $project->id])
            ->assertCreated()->json('id');

        Sanctum::actingAs($advisor);
        $this->postJson("/api/slots/{$slot->id}/leave", ['reason' => '出差'])
            ->assertOk()
            ->assertJsonPath('slot.status', 'leave');

        $this->assertSame('cancelled', \App\Models\Booking::find($bookingId)->status);

        // 学生收到本地模拟通知
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $s1->id,
            'type' => 'slot_released_leave',
        ]);

        // 请假后的时段不能再被学生预约
        Sanctum::actingAs(User::create(['name' => '丙', 'email' => 's3@e.com', 'password' => 'x', 'role' => 'student']));
        $this->postJson('/api/bookings', ['slot_id' => $slot->id])->assertStatus(409);
    }

    public function test_不能预约自己课题以外的项目(): void
    {
        [, , $s2, $project, $slot] = $this->makeWorld();

        Sanctum::actingAs($s2);
        // 学生乙把别人的课题挂在预约上 → 403
        $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'project_id' => $project->id])
            ->assertStatus(403);
    }
}
