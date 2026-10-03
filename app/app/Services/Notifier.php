<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 本地模拟通知：写入 notifications 表，并追加到 storage/logs/notifications.log。
 */
class Notifier
{
    public function send(
        User $to,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $to->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        Log::channel('notifications')->info(sprintf(
            '[→ %s(%s)] %s — %s',
            $to->name,
            $to->email,
            $title,
            $body ?? '',
        ));

        return $notification;
    }
}
