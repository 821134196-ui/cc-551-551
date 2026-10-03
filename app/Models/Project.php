<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'title', 'description', 'student_id', 'advisor_id',
        'deadline_at', 'defense_eligible', 'eligibility_note', 'eligibility_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'defense_eligible' => 'boolean',
            'eligibility_decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function manuscriptVersions(): HasMany
    {
        return $this->hasMany(ManuscriptVersion::class)->orderBy('version_no');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function guidanceRecords(): HasMany
    {
        return $this->hasMany(GuidanceRecord::class);
    }

    protected function latestVersion(): Attribute
    {
        return Attribute::get(fn () => $this->manuscriptVersions()->orderByDesc('version_no')->first());
    }
}
