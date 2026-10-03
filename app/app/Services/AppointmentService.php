<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DiscussionQuestion;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /**
     * 学生预约导师时段。
     * 同一时段仅允许一条有效预约 —— 由部分唯一索引兜底（并发下返回 409）。
     */
    public function book(User $student, int $slotId, ?string $topic, array $questions = []): Appointment
    {
        $slot = TimeSlot::findOrFail($slotId);

        if ($student->advisor_id !== $slot->advisor_id) {
            throw new HttpResponseException(response()->json([
                'message' => '只能预约自己导师的开放时段。',
            ], 403));
        }

        if ($slot->start_at->isPast()) {
            throw new HttpResponseException(response()->json([
                'message' => '该时段已开始，无法预约。',
            ], 422));
        }

        if ($slot->status !== TimeSlot::STATUS_OPEN
            || $slot->activeAppointment()->exists()) {
            throw new HttpResponseException(response()->json([
                'message' => '该时段已被其他学生预约，请选择其他时段。',
            ], 409));
        }

        return DB::transaction(function () use ($student, $slot, $topic, $questions) {
            // 事务内复查，竞态时由 appointments_active_slot_unique 索引兜底
            $locked = TimeSlot::query()->lockForUpdate()->find($slot->id);
            if ($locked->status !== TimeSlot::STATUS_OPEN
                || $locked->activeAppointment()->exists()) {
                throw new HttpResponseException(response()->json([
                    'message' => '该时段已被其他学生预约，请选择其他时段。',
                ], 409));
            }

            $appointment = Appointment::create([
                'time_slot_id' => $slot->id,
                'student_id' => $student->id,
                'advisor_id' => $slot->advisor_id,
                'topic' => $topic,
                'status' => Appointment::STATUS_BOOKED,
            ]);

            foreach ($questions as $content) {
                $content = trim((string) $content);
                if ($content !== '') {
                    DiscussionQuestion::create([
                        'appointment_id' => $appointment->id,
                        'student_id' => $student->id,
                        'content' => $content,
                    ]);
                }
            }

            $slot->update(['status' => TimeSlot::STATUS_BOOKED]);

            $this->notifier->send(
                $slot->advisor,
                'appointment_booked',
                '新的预约申请',
                sprintf('%s 预约了 %s 的指导时段。', $student->name, $slot->start_at->format('Y-m-d H:i')),
                ['appointment_id' => $appointment->id],
            );

            return $appointment->load(['timeSlot', 'student', 'questions']);
        });
    }

    /**
     * 学生取消预约，释放时段给其他学生。
     */
    public function cancelByStudent(Appointment $appointment): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_BOOKED) {
            throw new HttpResponseException(response()->json([
                'message' => '仅处于已预约状态的预约可以取消。',
            ], 422));
        }

        return DB::transaction(function () use ($appointment) {
            $appointment->update([
                'status' => Appointment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            $appointment->timeSlot->update(['status' => TimeSlot::STATUS_OPEN]);

            $this->notifier->send(
                $appointment->advisor,
                'appointment_cancelled',
                '学生取消了预约',
                sprintf('%s 取消了 %s 的预约。', $appointment->student->name,
                    $appointment->timeSlot->start_at->format('Y-m-d H:i')),
                ['appointment_id' => $appointment->id],
            );

            return $appointment;
        });
    }

    /**
     * 导师请假：释放区间内的未来时段。
     * - 开放时段 → cancelled；
     * - 已预约时段 → 预约 released_by_leave、时段 cancelled，并通知学生。
     */
    public function createLeave(User $advisor, array $data): void
    {
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        DB::transaction(function () use ($advisor, $data, $start, $end) {
            $advisor->leaves()->create([
                'start_at' => $start,
                'end_at' => $end,
                'reason' => $data['reason'],
            ]);

            $slots = TimeSlot::query()
                ->where('advisor_id', $advisor->id)
                ->where('start_at', '>=', now())
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->whereIn('status', [TimeSlot::STATUS_OPEN, TimeSlot::STATUS_BOOKED])
                ->with(['activeAppointment.student'])
                ->get();

            foreach ($slots as $slot) {
                $slot->update([
                    'status' => TimeSlot::STATUS_CANCELLED,
                    'cancel_reason' => '导师请假：' . $data['reason'],
                ]);

                $appointment = $slot->activeAppointment;
                if ($appointment && $appointment->status === Appointment::STATUS_BOOKED) {
                    $appointment->update([
                        'status' => Appointment::STATUS_RELEASED_BY_LEAVE,
                        'cancelled_at' => now(),
                    ]);

                    $this->notifier->send(
                        $appointment->student,
                        'slot_released_by_leave',
                        '导师请假，预约时段已释放',
                        sprintf(
                            '原定于 %s 的指导因导师请假（%s）取消，请重新预约其他时段。',
                            $slot->start_at->format('Y-m-d H:i'),
                            $data['reason'],
                        ),
                        ['appointment_id' => $appointment->id, 'time_slot_id' => $slot->id],
                    );
                }
            }
        });
    }
}
