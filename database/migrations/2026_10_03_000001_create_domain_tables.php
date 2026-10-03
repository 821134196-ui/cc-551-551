<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 毕业设计课题：一名学生 + 其选定导师
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            // 提交截止日（导师首页待办排序依据之一）
            $table->dateTime('deadline_at')->nullable();
            // 答辩资格：null 未确认 / true 具备 / false 不具备，仅导师可写
            $table->boolean('defense_eligible')->nullable();
            $table->text('eligibility_note')->nullable();
            $table->timestamp('eligibility_decided_at')->nullable();
            $table->timestamps();
        });

        // 导师开放 / 请假的时段
        Schema::create('advisor_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            // open 可预约 / booked 已预约 / leave 导师请假（释放，不再开放） / done 已完成指导
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['advisor_id', 'start_at']);
        });

        // 预约：同一时段只能有一条“有效(booked)”预约 —— 用部分唯一索引在数据库层兜底并发冲突；
        // 已取消/完成的历史预约不占索引，因此取消后时段可再次被预约。
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('advisor_slots')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            // 学生预约时填写的“准备讨论的问题”
            $table->text('questions')->nullable();
            // booked 已预约 / cancelled 学生取消或导师请假 / completed 已完成指导
            $table->string('status')->default('booked');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['slot_id', 'status']);
        });

        // SQLite 部分唯一索引：同一 slot 至多一条 booked
        DB::statement(
            "CREATE UNIQUE INDEX bookings_slot_active_unique ON bookings (slot_id) WHERE status = 'booked'"
        );

        // 稿件版本：同一课题内版本号递增，保留全部历史版本
        Schema::create('manuscript_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title');
            $table->text('note')->nullable();
            // active 有效 / withdrawn 已撤回（误传）。撤回后仍保留行，且指导记录继续指向它
            $table->string('status')->default('active');
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdraw_reason')->nullable();
            // true 表示学生/导师标记的最终稿（资格确认依据）
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'version_no']);
        });

        // 附件：多态挂在稿件版本或指导记录下，旧稿与附件都保留在本地目录
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 指导记录：约谈后导师针对“某一稿件版本”留下，一经创建不可修改
        Schema::create('guidance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            // 指导针对的版本；即使稿件被撤回，此引用与记录内容都不变
            $table->foreignId('manuscript_version_id')
                ->constrained('manuscript_versions')->restrictOnDelete();
            $table->text('summary');
            $table->timestamps();
        });

        // 评语（意见）：导师在指导记录中逐条提出
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guidance_record_id')->constrained('guidance_records')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            // open 待处理 / resolved 学生已回应待导师确认 / closed 导师关闭
            $table->string('status')->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status']);
        });

        // 学生在新稿中对评语的逐条回应
        Schema::create('comment_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // 回应所依附的新稿件版本（可追溯“在哪一版改的”）
            $table->foreignId('manuscript_version_id')->constrained('manuscript_versions')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        // 本地模拟通知：写库并写入 storage/logs/notifications.log
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('comment_responses');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('guidance_records');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('manuscript_versions');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('advisor_slots');
        Schema::dropIfExists('projects');
    }
};
