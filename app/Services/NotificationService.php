<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * 本地模拟通知：落库（前端轮询 /api/notifications），
 * 同时追加写入 storage/logs/notifications.log，模拟“推送到用户”。
 */
class NotificationService
{
    public function send(User $user, string $type, string $title, ?string $body = null, array $data = []): AppNotification
    {
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $line = sprintf(
            "[%s] -> 用户#%d(%s) [%s] %s%s\n",
            now()->toDateTimeString(),
            $user->id,
            $user->name,
            $type,
            $title,
            $body ? " | {$body}" : '',
        );

        // 模拟外部推送通道（本地仅追加写日志文件）
        File::append(storage_path('logs/notifications.log'), $line);

        return $notification;
    }
}
