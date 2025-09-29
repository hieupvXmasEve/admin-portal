<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'location',
        'gold_reward_amount',
        'max_participants',
        'qr_code',
        'organizer_type',
        'organizer_id',
        'status',
        'created_by_user_id',
        'is_manual',
        'is_historical',
        'created_by_admin_id'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'gold_reward_amount' => 'decimal:2',
        'is_manual' => 'boolean',
        'is_historical' => 'boolean'
    ];

    // Relationships
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)
            ->whereIn('status', ['registered', 'checked_in', 'completed']);
    }

    public function registeredParticipants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)
            ->where('status', 'registered');
    }

    public function checkedInParticipants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)
            ->where('status', 'checked_in');
    }

    public function completedParticipants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)
            ->where('status', 'completed');
    }

    // Status Methods
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isManual(): bool
    {
        return $this->is_manual;
    }

    public function isHistorical(): bool
    {
        return $this->is_historical;
    }

    public function canRegister(): bool
    {
        return $this->isPublished()
            && !$this->hasReachedCapacity()
            && $this->start_time->isFuture();
    }

    public function canCheckIn(): bool
    {
        return $this->isPublished()
            && $this->start_time->isPast()
            && $this->end_time->isFuture();
    }

    public function hasStarted(): bool
    {
        return $this->start_time->isPast();
    }

    public function hasEnded(): bool
    {
        return $this->end_time->isPast();
    }

    // Participant Methods
    public function getRegisteredCount(): int
    {
        return $this->participants()
            ->whereIn('status', ['registered', 'checked_in', 'completed'])
            ->count();
    }

    public function getCheckedInCount(): int
    {
        return $this->participants()
            ->whereIn('status', ['checked_in', 'completed'])
            ->count();
    }

    public function getCompletedCount(): int
    {
        return $this->participants()
            ->where('status', 'completed')
            ->count();
    }

    public function getCancelledCount(): int
    {
        return $this->participants()
            ->where('status', 'cancelled')
            ->count();
    }

    public function hasReachedCapacity(): bool
    {
        if ($this->max_participants === null) {
            return false;
        }

        return $this->getRegisteredCount() >= $this->max_participants;
    }

    public function getAvailableSpots(): ?int
    {
        if ($this->max_participants === null) {
            return null;
        }

        return max(0, $this->max_participants - $this->getRegisteredCount());
    }

    // Business Logic Methods
    public function isStudentRegistered(int $studentId): bool
    {
        return $this->participants()
            ->where('student_id', $studentId)
            ->whereIn('status', ['registered', 'checked_in', 'completed'])
            ->exists();
    }

    public function getStudentParticipation(int $studentId): ?EventParticipant
    {
        return $this->participants()
            ->where('student_id', $studentId)
            ->first();
    }

    public function getTotalGoldAwarded(): float
    {
        return $this->participants()
            ->where('gold_awarded', true)
            ->count() * $this->gold_reward_amount;
    }

    public function getParticipationRate(): float
    {
        $registered = $this->getRegisteredCount();
        if ($registered === 0) {
            return 0;
        }

        return ($this->getCheckedInCount() / $registered) * 100;
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeForCampus($query, int $campusId)
    {
        return $query->where('campus_id', $campusId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('start_time', '<=', now())
            ->where('end_time', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('end_time', '<', now());
    }

    public function scopeManual($query)
    {
        return $query->where('is_manual', true);
    }

    public function scopeHistorical($query)
    {
        return $query->where('is_historical', true);
    }

    public function scopeRegular($query)
    {
        return $query->where('is_manual', false);
    }
}
