<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'unread_count' => AppNotification::where('user_id', $request->user()->id)
                ->whereNull('read_at')->count(),
            'data' => AppNotification::where('user_id', $request->user()->id)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (AppNotification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function markRead(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => '全部通知已标记为已读。']);
    }
}
