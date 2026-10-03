<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\GuidanceController;
use App\Http\Controllers\ManuscriptController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 公开路由
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| 需要登录（Sanctum token）
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/directory/advisors', [UserDirectoryController::class, 'advisors']);

    // 通知（本地模拟）
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // 角色首页待办
    Route::get('/dashboard/student', [DashboardController::class, 'student'])
        ->middleware('role:student');
    Route::get('/dashboard/advisor', [DashboardController::class, 'advisor'])
        ->middleware('role:advisor');

    // 课题
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware('role:student');
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects/{project}/deadline', [ProjectController::class, 'setDeadline']);

    // 导师时段
    Route::get('/slots/open', [SlotController::class, 'openSlots']);
    Route::get('/slots/mine', [SlotController::class, 'mine'])->middleware('role:advisor');
    Route::post('/slots', [SlotController::class, 'store'])->middleware('role:advisor');
    Route::post('/slots/{slot}/leave', [SlotController::class, 'leave'])->middleware('role:advisor');
    Route::post('/slots/{slot}/reopen', [SlotController::class, 'reopen'])->middleware('role:advisor');

    // 预约
    Route::post('/bookings', [BookingController::class, 'store'])->middleware('role:student');
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

    // 稿件版本（学生上传 / 撤回）
    Route::post('/projects/{project}/manuscripts', [ManuscriptController::class, 'store']);
    Route::get('/manuscripts/{version}', [ManuscriptController::class, 'show']);
    Route::post('/manuscripts/{version}/withdraw', [ManuscriptController::class, 'withdraw']);

    // 指导记录（导师，只读 + 创建，无编辑/删除）
    Route::post('/projects/{project}/guidance', [GuidanceController::class, 'store']);
    Route::get('/projects/{project}/guidance/{recordId}', [GuidanceController::class, 'show']);

    // 评语：仅导师可关闭 / 重开
    Route::post('/comments/{comment}/close', [CommentController::class, 'close']);
    Route::post('/comments/{comment}/reopen', [CommentController::class, 'reopen']);

    // 答辩资格：仅导师
    Route::post('/projects/{project}/eligibility', [EligibilityController::class, 'decide']);

    // 附件下载（策略鉴权，跨导师 403）
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
});
