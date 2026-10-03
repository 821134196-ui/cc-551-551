<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'student_no', 'title'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdvisor(): bool
    {
        return $this->role === 'advisor';
    }

    /** 学生发起的课题 */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'student_id');
    }

    /** 导师指导的课题 */
    public function advisedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'advisor_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AdvisorSlot::class, 'advisor_id');
    }
}
