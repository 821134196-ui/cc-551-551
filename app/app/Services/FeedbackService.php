<?php

namespace App\Services;

use App\Models\FeedbackItem;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class FeedbackService
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** 仅导师可关闭意见，学生无权关闭。 */
    public function close(User $advisor, FeedbackItem $item): FeedbackItem
    {
        if ($item->advisor_id !== $advisor->id) {
            throw new HttpResponseException(response()->json(['message' => '无权操作此意见。'], 403));
        }

        $item->update(['status' => FeedbackItem::STATUS_CLOSED, 'closed_at' => now()]);

        $this->notifier->send(
            $item->student,
            'feedback_closed',
            '导师关闭了一条修改意见',
            sprintf('第 %d 条意见已确认关闭。', $item->sequence),
            ['feedback_item_id' => $item->id],
        );

        return $item;
    }

    /** 导师复核学生回应后认为仍需修改，可重新打开。 */
    public function reopen(User $advisor, FeedbackItem $item): FeedbackItem
    {
        if ($item->advisor_id !== $advisor->id) {
            throw new HttpResponseException(response()->json(['message' => '无权操作此意见。'], 403));
        }

        $item->update(['status' => FeedbackItem::STATUS_OPEN, 'closed_at' => null]);

        $this->notifier->send(
            $item->student,
            'feedback_reopened',
            '导师重新打开了一条修改意见',
            sprintf('第 %d 条意见需要继续修改。', $item->sequence),
            ['feedback_item_id' => $item->id],
        );

        return $item;
    }
}
