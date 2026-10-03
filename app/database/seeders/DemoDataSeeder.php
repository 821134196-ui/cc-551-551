<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DefenseDecision;
use App\Models\FeedbackItem;
use App\Models\FeedbackResponse;
use App\Models\GuidanceSession;
use App\Models\Submission;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('attachments')->deleteDirectory('');

        DB::table('feedback_responses')->delete();
        DB::table('feedback_items')->delete();
        DB::table('guidance_sessions')->delete();
        DB::table('discussion_questions')->delete();
        DB::table('appointments')->delete();
        DB::table('time_slots')->delete();
        DB::table('leave_requests')->delete();
        DB::table('submissions')->delete();
        DB::table('defense_decisions')->delete();
        DB::table('attachments')->delete();
        DB::table('notifications')->delete();
        User::query()->delete();

        $advisorA = User::create([
            'name' => '王明（导师）',
            'email' => 'wangming@demo.edu',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADVISOR,
            'api_token' => 'token-advisor-wang',
        ]);

        $advisorB = User::create([
            'name' => '李华（导师）',
            'email' => 'lihua@demo.edu',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADVISOR,
            'api_token' => 'token-advisor-li',
        ]);

        $zhang = User::create([
            'name' => '张三（学生）',
            'email' => 'zhangsan@demo.edu',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STUDENT,
            'advisor_id' => $advisorA->id,
            'api_token' => 'token-student-zhang',
        ]);

        $zhao = User::create([
            'name' => '赵四（学生）',
            'email' => 'zhaosi@demo.edu',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STUDENT,
            'advisor_id' => $advisorB->id,
            'api_token' => 'token-student-zhao',
        ]);

        // 导师 A 的第二位学生：用于演示同一时段抢约冲突
        $sun = User::create([
            'name' => '孙五（学生）',
            'email' => 'sunwu@demo.edu',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STUDENT,
            'advisor_id' => $advisorA->id,
            'api_token' => 'token-student-sun',
        ]);

        $base = Carbon::tomorrow()->setTime(10, 0);

        // 导师 A 的三个时段：10:00（被张三预约）、11:00（开放）、14:00（开放）
        $slot1 = TimeSlot::create([
            'advisor_id' => $advisorA->id,
            'start_at' => $base->copy(),
            'end_at' => $base->copy()->addHour(),
            'location' => '工科楼 302',
            'status' => TimeSlot::STATUS_BOOKED,
        ]);
        $slot2 = TimeSlot::create([
            'advisor_id' => $advisorA->id,
            'start_at' => $base->copy()->addHour(),
            'end_at' => $base->copy()->addHours(2),
            'location' => '工科楼 302',
            'status' => TimeSlot::STATUS_OPEN,
        ]);
        $slot3 = TimeSlot::create([
            'advisor_id' => $advisorA->id,
            'start_at' => $base->copy()->addHours(4),
            'end_at' => $base->copy()->addHours(5),
            'location' => '线上会议室',
            'status' => TimeSlot::STATUS_OPEN,
        ]);

        // 导师 B 的开放时段（张三无权预约）
        TimeSlot::create([
            'advisor_id' => $advisorB->id,
            'start_at' => $base->copy()->addHours(2),
            'end_at' => $base->copy()->addHours(3),
            'location' => '理科楼 211',
            'status' => TimeSlot::STATUS_OPEN,
        ]);

        $appointment = Appointment::create([
            'time_slot_id' => $slot1->id,
            'student_id' => $zhang->id,
            'advisor_id' => $advisorA->id,
            'topic' => '开题报告初稿讨论',
            'status' => Appointment::STATUS_BOOKED,
            'submit_deadline' => Carbon::tomorrow()->setTime(8, 0),
        ]);

        DB::table('discussion_questions')->insert([
            ['appointment_id' => $appointment->id, 'student_id' => $zhang->id,
             'content' => '研究问题三的可行性如何论证？', 'created_at' => now(), 'updated_at' => now()],
            ['appointment_id' => $appointment->id, 'student_id' => $zhang->id,
             'content' => '第二章文献综述结构是否需要调整？', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ===== 版本追溯演示数据 =====

        // 第 1 版：已提交，已被第一次指导引用
        $v1 = Submission::create([
            'student_id' => $zhang->id, 'advisor_id' => $advisorA->id,
            'version' => 1, 'title' => '基于机器学习的毕业设计开题报告',
            'note' => '初稿，请老师重点看研究方法部分。',
            'status' => Submission::STATUS_SUBMITTED,
            'created_at' => Carbon::now()->subDays(10), 'updated_at' => Carbon::now()->subDays(10),
        ]);
        $this->fakeAttachment($v1, '开题报告_v1.docx', $zhang, Carbon::now()->subDays(10));

        $g1 = GuidanceSession::create([
            'appointment_id' => $appointment->id,
            'submission_id' => $v1->id,
            'advisor_id' => $advisorA->id,
            'student_id' => $zhang->id,
            'overall_comment' => '框架基本合理，研究方法与文献综述需要加强。',
            'created_at' => Carbon::now()->subDays(8), 'updated_at' => Carbon::now()->subDays(8),
        ]);
        $f1 = FeedbackItem::create([
            'guidance_session_id' => $g1->id, 'advisor_id' => $advisorA->id, 'student_id' => $zhang->id,
            'sequence' => 1, 'content' => '研究方法缺少对照实验设计，需要补充评估指标。',
            'status' => FeedbackItem::STATUS_CLOSED, 'closed_at' => Carbon::now()->subDays(3),
            'created_at' => Carbon::now()->subDays(8), 'updated_at' => Carbon::now()->subDays(3),
        ]);
        $f2 = FeedbackItem::create([
            'guidance_session_id' => $g1->id, 'advisor_id' => $advisorA->id, 'student_id' => $zhang->id,
            'sequence' => 2, 'content' => '文献综述缺少近三年的相关工作，至少补充 5 篇。',
            'status' => FeedbackItem::STATUS_ANSWERED,
            'created_at' => Carbon::now()->subDays(8), 'updated_at' => Carbon::now()->subDays(4),
        ]);

        // 第 2 版：学生误传后撤回；指导记录仍引用第 1 版，不受影响
        $v2 = Submission::create([
            'student_id' => $zhang->id, 'advisor_id' => $advisorA->id,
            'version' => 2, 'title' => '开题报告_误传版本',
            'note' => '（此版本上传错文件）',
            'status' => Submission::STATUS_WITHDRAWN,
            'withdraw_reason' => '误传了其他课程的作业文件',
            'withdrawn_at' => Carbon::now()->subDays(5),
            'previous_id' => $v1->id,
            'created_at' => Carbon::now()->subDays(6), 'updated_at' => Carbon::now()->subDays(5),
        ]);
        $this->fakeAttachment($v2, '错误的文件.docx', $zhang, Carbon::now()->subDays(6));

        // 第 3 版：正式修改稿，学生在本版中逐条回应第 1 版指导意见
        $v3 = Submission::create([
            'student_id' => $zhang->id, 'advisor_id' => $advisorA->id,
            'version' => 3, 'title' => '基于机器学习的毕业设计开题报告',
            'note' => '已按指导意见修改，补充了实验设计与最新文献。',
            'status' => Submission::STATUS_SUBMITTED,
            'previous_id' => $v2->id,
            'created_at' => Carbon::now()->subDays(4), 'updated_at' => Carbon::now()->subDays(4),
        ]);
        $this->fakeAttachment($v3, '开题报告_v3.docx', $zhang, Carbon::now()->subDays(4));

        FeedbackResponse::create([
            'feedback_item_id' => $f1->id, 'submission_id' => $v3->id, 'student_id' => $zhang->id,
            'reply' => '已在 3.2 节补充对照组与准确率/F1 两项评估指标。',
            'created_at' => Carbon::now()->subDays(4), 'updated_at' => Carbon::now()->subDays(4),
        ]);
        FeedbackResponse::create([
            'feedback_item_id' => $f2->id, 'submission_id' => $v3->id, 'student_id' => $zhang->id,
            'reply' => '已补充 6 篇 2023-2025 年文献，见参考文献 [8]-[13]。',
            'created_at' => Carbon::now()->subDays(4), 'updated_at' => Carbon::now()->subDays(4),
        ]);

        // 导师第二次指导：针对第 3 版；遗留 1 条未关闭意见（学生首页突出显示）
        $g2 = GuidanceSession::create([
            'appointment_id' => $appointment->id,
            'submission_id' => $v3->id,
            'advisor_id' => $advisorA->id,
            'student_id' => $zhang->id,
            'overall_comment' => '实验设计已达标，文献综述仍需按主题分类组织。',
            'created_at' => Carbon::now()->subDays(2), 'updated_at' => Carbon::now()->subDays(2),
        ]);
        FeedbackItem::create([
            'guidance_session_id' => $g2->id, 'advisor_id' => $advisorA->id, 'student_id' => $zhang->id,
            'sequence' => 1,
            'content' => '文献综述请按"方法类/数据类/应用类"三个主题重新分类组织。',
            'status' => FeedbackItem::STATUS_OPEN,
            'created_at' => Carbon::now()->subDays(2), 'updated_at' => Carbon::now()->subDays(2),
        ]);

        app(Notifier::class)->send(
            $zhang, 'demo_seed', '演示数据已就绪',
            '可用 zhangsan@demo.edu / password 登录，首页可见待处理意见。'
        );
    }

    private function fakeAttachment(Submission $submission, string $name, User $uploader, Carbon $at): void
    {
        $dir = 'submissions/' . $submission->id;
        Storage::disk('attachments')->put(
            $path = $dir . '/' . $name,
            "这是《{$submission->title}》第 {$submission->version} 版的模拟附件内容。\r\n生成时间：{$at->toDateTimeString()}\r\n"
        );

        DB::table('attachments')->insert([
            'attachable_type' => $submission->getMorphClass(),
            'attachable_id' => $submission->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $name,
            'path' => $path,
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => Storage::disk('attachments')->size($path),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
