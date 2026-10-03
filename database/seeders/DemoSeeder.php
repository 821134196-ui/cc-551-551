<?php

namespace Database\Seeders;

use App\Models\AdvisorSlot;
use App\Models\Booking;
use App\Models\Project;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ManuscriptService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 演示附件写入真实本地磁盘 storage/app/attachments
        $makeFile = function (string $name, string $content): UploadedFile {
            $tmp = tempnam(sys_get_temp_dir(), 'demo_');
            file_put_contents($tmp, $content);
            // test=true：允许在非 HTTP 上下文中存储上传文件
            return new UploadedFile($tmp, $name, null, null, true);
        };

        // 两名导师、两名学生（密码均为 password）
        $advisorWang = User::create(['name' => '王雅琴', 'email' => 'wang@example.edu', 'password' => 'password', 'role' => 'advisor', 'title' => '教授']);
        $advisorLi = User::create(['name' => '李国栋', 'email' => 'li@example.edu', 'password' => 'password', 'role' => 'advisor', 'title' => '副教授']);
        $studentZhang = User::create(['name' => '张一鸣', 'email' => 'zhang@example.edu', 'password' => 'password', 'role' => 'student', 'student_no' => '2021001']);
        $studentChen = User::create(['name' => '陈思琪', 'email' => 'chen@example.edu', 'password' => 'password', 'role' => 'student', 'student_no' => '2021002']);

        $bookingService = app(BookingService::class);
        $manuscriptService = app(ManuscriptService::class);

        // 张一鸣 的课题：导师王雅琴
        $project = Project::create([
            'title' => '基于联邦学习的校园能耗预测系统',
            'description' => '研究多校园数据不出域条件下的能耗联合建模。',
            'student_id' => $studentZhang->id,
            'advisor_id' => $advisorWang->id,
            'deadline_at' => now()->addDays(10),
        ]);

        // 王雅琴开放若干时段
        $slotA = AdvisorSlot::create(['advisor_id' => $advisorWang->id, 'start_at' => now()->addDay()->setTime(10, 0), 'end_at' => now()->addDay()->setTime(11, 0), 'status' => 'open']);
        $slotB = AdvisorSlot::create(['advisor_id' => $advisorWang->id, 'start_at' => now()->addDays(3)->setTime(14, 0), 'end_at' => now()->addDays(3)->setTime(15, 0), 'status' => 'open']);
        $slotC = AdvisorSlot::create(['advisor_id' => $advisorWang->id, 'start_at' => now()->addDays(5)->setTime(9, 0), 'end_at' => now()->addDays(5)->setTime(10, 0), 'status' => 'open']);
        // 李国栋的开放时段（用于跨导师访问演示）
        AdvisorSlot::create(['advisor_id' => $advisorLi->id, 'start_at' => now()->addDays(2)->setTime(16, 0), 'end_at' => now()->addDays(2)->setTime(17, 0), 'status' => 'open']);

        // 张一鸣 预约 slotA
        $booking = $bookingService->book($slotA, $studentZhang, $project, '联邦聚合时客户端掉线如何处理？特征维度不一致怎么办？');

        // 再预约一场未来约谈（保持 booked，出现在导师首页“即将到来的预约”待办中）
        $bookingService->book($slotB, $studentZhang, $project, '第4章实验结果如何呈现更清晰？');

        // 提交 v1
        $v1File = $makeFile('thesis-v1.docx', '第1版：引言与系统设计初稿');
        $v1 = $manuscriptService->submitVersion($project, $studentZhang, '毕业论文 v1', '初稿，请老师重点看研究问题定义。', [$v1File], [], $booking);

        // 导师约谈后针对 v1 留下指导记录与两条评语，booking 完成
        $annotFile = $makeFile('批注-v1.pdf', '导师批注：研究问题需进一步聚焦');
        $record1 = $manuscriptService->createGuidance(
            $project,
            $advisorWang,
            $v1,
            '整体方向可行，但研究问题过大，实验设计缺少基线。',
            [
                '研究问题需要收敛到“掉线鲁棒性”这一个点，删掉无关章节。',
                '实验部分补充与 FedAvg、FedProx 的对比基线。',
            ],
            [$annotFile],
            $booking,
        );

        // 张一鸣 提交 v2，逐条回应两条评语（一条暂不回应，保留 open 演示未处理意见）
        $v2File = $makeFile('thesis-v2.docx', '第2版：收敛研究问题，补实验基线');
        $v2 = $manuscriptService->submitVersion(
            $project,
            $studentZhang,
            '毕业论文 v2',
            '已按上次意见修改，实验基线仍在跑。',
            [$v2File],
            [
                ['comment_id' => $record1->comments[0]->id, 'content' => '已将研究问题收敛为客户端掉线场景，删除了原第3章。'],
            ],
        );

        // 导师确认第 1 条已修改到位 → 关闭；第 2 条仍 open（学生首页突出显示）
        $manuscriptService->closeComment($record1->comments[0], $advisorWang, '收敛后的问题表述清晰，同意关闭。');

        // 陈思琪 的课题：导师李国栋（跨导师隔离演示）
        Project::create([
            'title' => '低轨卫星网络路由协议仿真',
            'description' => '面向星地链路频繁切换的路由策略。',
            'student_id' => $studentChen->id,
            'advisor_id' => $advisorLi->id,
            'deadline_at' => now()->addDays(20),
        ]);

        $this->command->info('演示数据已创建：');
        $this->command->table(
            ['角色', '姓名', '登录邮箱', '密码'],
            [
                ['学生', '张一鸣', 'zhang@example.edu', 'password'],
                ['学生', '陈思琪', 'chen@example.edu', 'password'],
                ['导师', '王雅琴', 'wang@example.edu', 'password'],
                ['导师', '李国栋', 'li@example.edu', 'password'],
            ]
        );
    }
}
