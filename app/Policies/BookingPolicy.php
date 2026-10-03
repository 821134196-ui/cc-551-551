<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        // 预约学生本人，或该时段所属导师
        return $booking->student_id === $user->id
            || $booking->slot->advisor_id === $user->id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id && $user->isStudent();
    }
}
