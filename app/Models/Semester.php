<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
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

    /**
     * Get the currently active semester (manually set)
     */
    public static function getActiveSemester(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Get the next semester that should be activated based on start_date
     */
    public static function getNextActiveSemester(): ?self
    {
        $now = Carbon::now();

        return static::where('start_date', '>', $now)
            ->where('is_archived', false)
            ->orderBy('start_date', 'asc')
            ->first();
    }

    /**
     * Check if this semester can be activated
     */
    public function canBeActivated(): bool
    {
        $now = Carbon::now();

        // Cannot activate if archived
        if ($this->is_archived) {
            return false;
        }

        // Cannot activate if start_date has passed
        if ($this->start_date && $this->start_date->lte($now)) {
            return false;
        }

        // Check if this is the next semester to be activated
        $nextSemester = static::getNextActiveSemester();
        if (!$nextSemester || $nextSemester->id !== $this->id) {
            return false;
        }

        return true;
    }

    /**
     * Check if this semester can change its active status
     * Cannot change active status during the semester period
     */
    public function canChangeActiveStatus(): bool
    {
        $now = Carbon::now();

        // Cannot change if semester is currently running (between start_date and end_date)
        if (
            $this->start_date && $this->end_date &&
            $now->gte($this->start_date) && $now->lte($this->end_date)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Activate this semester and deactivate all others
     */
    public function activate(): bool
    {
        if (!$this->canBeActivated()) {
            return false;
        }

        return DB::transaction(function () {
            // Deactivate all other semesters
            static::where('id', '!=', $this->id)->update(['is_active' => false]);

            // Activate this semester
            $this->update(['is_active' => true]);

            return true;
        });
    }

    /**
     * Deactivate this semester
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Check if semester should be automatically deactivated (past end_date)
     */
    public function shouldBeDeactivated(): bool
    {
        if (!$this->is_active || !$this->end_date) {
            return false;
        }

        $now = Carbon::now();
        return $now->gt($this->end_date);
    }

    /**
     * Automatically deactivate expired semesters
     */
    public static function deactivateExpiredSemesters(): int
    {
        $now = Carbon::now();

        return (int)static::where('is_active', true)
            ->where('end_date', '<', $now)
            ->update(['is_active' => false]);
    }

    /**
     * Get validation error for activation attempt
     */
    public function getActivationError(): ?string
    {
        $now = Carbon::now();

        if ($this->is_archived) {
            return 'Cannot activate an archived semester.';
        }

        if ($this->start_date && $this->start_date->lte($now)) {
            return 'Cannot activate a semester that has already started.';
        }

        $nextSemester = static::getNextActiveSemester();
        if (!$nextSemester) {
            return 'No upcoming semesters available for activation.';
        }

        if ($nextSemester->id !== $this->id) {
            return "Only the next semester ({$nextSemester->name}) can be activated.";
        }

        return null;
    }

    /**
     * Get validation error for changing active status
     */
    public function getActiveStatusChangeError(): ?string
    {
        if (!$this->canChangeActiveStatus()) {
            return 'Cannot change active status during the semester period.';
        }

        return null;
    }
}
