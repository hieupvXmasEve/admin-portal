<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Semester extends Model
{
    /** @use HasFactory<\Database\Factories\SemesterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'start_date',
        'end_date',
        'enrollment_start_date',
        'enrollment_end_date',
        'is_active',
        'is_archived',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'enrollment_start_date' => 'datetime',
        'enrollment_end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class, 'semester_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function semesterOfferings(): HasMany
    {
        return $this->hasMany(SemesterUnitOffering::class);
    }

    public function isArchived(): bool
    {
        return $this->is_archived;
    }

    public function canEdit(): bool
    {
        return !$this->isArchived();
    }

    public function canDelete(): bool
    {
        return !$this->isArchived() && !$this->is_active;
    }

    public function isRegistrationOpen(): bool
    {
        if (!$this->enrollment_start_date || !$this->enrollment_end_date) {
            return false;
        }

        $now = Carbon::now();
        return $now->between($this->enrollment_start_date, $this->enrollment_end_date);
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        return $now->between($this->start_date, $this->end_date);
    }

    public function getDurationInWeeks(): int
    {
        return $this->start_date->diffInWeeks($this->end_date);
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('start_date', '>', $now);
    }

    public function scopePast(Builder $query): void
    {
        $now = Carbon::now();
        $query->where('end_date', '<', $now);
    }

    public function scopeNotArchived(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('is_archived', true);
    }

    public function scopeByCode(Builder $query, string $code): void
    {
        $query->where('code', $code);
    }
}
