<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 答辩资格：仅导师可根据最终稿 + 未解决意见确认
        Schema::create('defense_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('final_submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->boolean('eligible');
            $table->text('comment')->nullable();
            $table->unsignedInteger('unresolved_count')->default(0);
            $table->dateTime('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_decisions');
    }
};
