<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic')->nullable();
            // booked=已预约 completed=已完成指导 cancelled=学生取消 released_by_leave=导师请假释放
            $table->enum('status', ['booked', 'completed', 'cancelled', 'released_by_leave'])
                ->default('booked');
            // 导师要求的稿件提交截止时间
            $table->dateTime('submit_deadline')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['advisor_id', 'status']);
        });

        // 同一时段至多存在一条"有效"预约（已预约/已完成）。
        // cancelled / released_by_leave 不计入，因此取消后时段可以被他人重新预约。
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX appointments_active_slot_unique
                 ON appointments (time_slot_id)
                 WHERE status IN ("booked", "completed")'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
