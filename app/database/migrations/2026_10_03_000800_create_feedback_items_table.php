<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 逐条修改意见：open 待处理 / answered 学生已在新稿回应 / closed 导师确认关闭
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guidance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->text('content');
            $table->enum('status', ['open', 'answered', 'closed'])->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
            $table->index(['advisor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');
    }
};
