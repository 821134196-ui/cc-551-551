<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 学生在提交新稿时对旧意见的逐条回应，记录回应所在的新版本。
        // 只追加，构成 评语(v1) → 回应(v2) → 新评语(v2) 的版本追溯链。
        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('reply');
            $table->timestamps();
            $table->index(['feedback_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
    }
};
