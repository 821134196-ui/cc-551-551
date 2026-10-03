<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 稿件：每次提交为一条不可变版本；撤回只置状态、保留文件与历史。
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('note')->nullable();
            // submitted=正常版本 withdrawn=学生撤回（文件保留）
            $table->enum('status', ['submitted', 'withdrawn'])->default('submitted');
            $table->text('withdraw_reason')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->foreignId('previous_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
