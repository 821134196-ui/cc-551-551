<?php

namespace App\Services;

use App\Models\AdvisorSlot;
use App\Models\Booking;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * 学生预约导师时段。
     *
     * 冲突防护有两层：
     *  1. 事务内再次读取并校验 slot.status = open；
     *  2. bookings.slot_id 上的“部分唯一索引”（WHERE status='booked'）兜底并发写入。
     */
    public function book(AdvisorSlot $slot, User $student, ?Project $project, ?string $questions): Booking
    {
        return DB::transaction(function () use ($slot, $student, $project, $questions) {
            /** @var AdvisorSlot $lockedSlot */
            $lockedSlot = AdvisorSlot::query()->lockForUpdate()->find($slot->id);

            if ($lockedSlot->status !== 'open') {
                throw ValidationException::withMessages([
                    'slot_id' => '该时段已被预约或不可预约，请选择其他时段。',
                ])->status(409);
            }

            if ($lockedSlot->start_at->isPast()) {
                throw ValidationException::withMessages([
                    'slot_id' => '该时段已开始，无法预约。',
                ])->status(422);
            }

            if ($project && $project->student_id !== $student->id) {
                throw ValidationException::withMessages([
                    'project_id' => '只能为自己的课题发起预约。',
                ])->status(403);
            }

            try {
                $booking = Booking::create([
                    'slot_id' => $lockedSlot->id,
                    'student_id' => $student->id,
                    'project_id' => $project?->id,
                    'questions' => $questions,
                    'status' => 'booked',
                ]);
            } catch (QueryException $e) {
                // 唯一索引冲突（SQLite SQLSTATE 23000）
                throw ValidationException::withMessages([
                    'slot_id' => '该时段刚刚被其他学生预约，请选择其他时段。',
                ])->status(409);
            }

            $lockedSlot->update(['status' => 'booked']);

            $this->notifications->send(
                $lockedSlot->advisor,
                'booking_created',
                '新的约谈预约',
                sprintf('%s 预约了 %s 的时段', $student->name, $lockedSlot->start_at->toDateTimeString()),
                ['booking_id' => $booking->id, 'slot_id' => $lockedSlot->id],
            );

            return $booking->load(['slot', 'student', 'project']);
        });
    }

    /** 学生主动取消：时段重新开放给其他学生 */
    public function cancelByStudent(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            /** @var Booking $fresh */
            $fresh = Booking::query()->lockForUpdate()->find($booking->id);

            if ($fresh->status !== 'booked') {
                throw ValidationException::withMessages(['booking' => '该预约已不可取消。'])->status(422);
            }

            $fresh->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $fresh->slot->update(['status' => 'open']);

            $this->notifications->send(
                $fresh->slot->advisor,
                'booking_cancelled',
                '学生取消了预约',
                sprintf('%s 取消了 %s 的时段', $fresh->student->name, $fresh->slot->start_at->toDateTimeString()),
                ['booking_id' => $fresh->id],
            );

            return $fresh;
        });
    }

    /**
     * 导师请假：该时段释放。
     * 若已有学生预约，预约取消并通知学生改约。
     */
    public function advisorLeave(AdvisorSlot $slot, ?string $reason = null): AdvisorSlot
    {
        return DB::transaction(function () use ($slot, $reason) {
            /** @var AdvisorSlot $fresh */
            $fresh = AdvisorSlot::query()->lockForUpdate()->find($slot->id);

            $booking = $fresh->booking()->where('status', 'booked')->first();

            if ($booking) {
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                $this->notifications->send(
                    $booking->student,
                    'slot_released_leave',
                    '导师请假，预约时段已释放',
                    sprintf(
                        '您预约的 %s 时段因导师请假%s已取消，请重新选择时段。',
                        $fresh->start_at->toDateTimeString(),
                        $reason ? "（{$reason}）" : '',
                    ),
                    ['slot_id' => $fresh->id, 'booking_id' => $booking->id],
                );
            }

            // leave = 导师请假占位，不再对外开放
            $fresh->update(['status' => 'leave']);

            return $fresh;
        });
    }
}
