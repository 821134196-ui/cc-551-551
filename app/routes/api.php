<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefenseController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GuidanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TimeSlotController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);

    // 时段
    Route::get('/time-slots', [TimeSlotController::class, 'index']);
    Route::get('/time-slots/{timeSlot}', [TimeSlotController::class, 'show']);
    Route::post('/time-slots', [TimeSlotController::class, 'store'])
        ->middleware('role:advisor');

    // 预约
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('role:student');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
        ->middleware('role:student');
    Route::post('/appointments/{appointment}/deadline', [AppointmentController::class, 'setDeadline'])
        ->middleware('role:advisor');

    // 导师请假
    Route::get('/leaves', [LeaveController::class, 'index'])->middleware('role:advisor');
    Route::post('/leaves', [LeaveController::class, 'store'])->middleware('role:advisor');

    // 稿件版本
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::post('/submissions', [SubmissionController::class, 'store'])
        ->middleware('role:student');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::post('/submissions/{submission}/withdraw', [SubmissionController::class, 'withdraw'])
        ->middleware('role:student');

    // 指导评语（约谈后、针对某版本；不可修改）
    Route::get('/guidance/{guidance}', [GuidanceController::class, 'show']);
    Route::post('/appointments/{appointment}/guidance', [GuidanceController::class, 'store'])
        ->middleware('role:advisor');

    // 意见关闭/重开：只有导师
    Route::post('/feedback/{feedbackItem}/close', [FeedbackController::class, 'close'])
        ->middleware('role:advisor');
    Route::post('/feedback/{feedbackItem}/reopen', [FeedbackController::class, 'reopen'])
        ->middleware('role:advisor');

    // 答辩资格
    Route::get('/students/{student}/defense', [DefenseController::class, 'show']);
    Route::post('/students/{student}/defense', [DefenseController::class, 'decide'])
        ->middleware('role:advisor');

    // 附件下载（归属校验后放行）
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
});
