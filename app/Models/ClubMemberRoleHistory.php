<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubMemberRoleHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_member_roles_history';

    protected $fillable = [
        'club_member_id',
        'old_role',
        'new_role',
        'changed_by',
        'change_reason',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the club member that owns this role history.
     */
    public function clubMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class);
    }

    /**
     * Get the student who made the role change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'changed_by');
    }

    /**
     * Check if this role change is currently active.
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Scope to get active role changes.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to get role changes for a specific member.
     */
    public function scopeForMember($query, int $clubMemberId)
    {
        return $query->where('club_member_id', $clubMemberId);
    }

    /**
     * Scope to get role changes ordered by date.
     */
    public function scopeOrderedByDate($query)
    {
        return $query->orderBy('started_at', 'desc');
    }

    /**
     * Get the duration of this role in days.
     */
    public function getDurationInDays(): ?int
    {
        if ($this->ended_at === null) {
            return null; // Still active
        }

        return $this->started_at->diffInDays($this->ended_at);
    }

    /**
     * Get a formatted description of the role change.
     */
    public function getChangeDescription(): string
    {
        if ($this->old_role === null) {
            return "Assigned role: {$this->new_role}";
        }

        return "Changed from {$this->old_role} to {$this->new_role}";
    }
}
