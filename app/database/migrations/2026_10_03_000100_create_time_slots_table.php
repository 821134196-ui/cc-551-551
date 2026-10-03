<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('location')->default('线上会议室');
            // open=可预约 booked=已被预约 cancelled=导师取消/请假释放
            $table->enum('status', ['open', 'booked', 'cancelled'])->default('open');
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['advisor_id', 'start_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
