<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADVISOR = 'advisor';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'advisor_id',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isAdvisor(): bool
    {
        return $this->role === self::ROLE_ADVISOR;
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'advisor_id');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class, 'advisor_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'advisor_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, $this->isAdvisor() ? 'advisor_id' : 'student_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
