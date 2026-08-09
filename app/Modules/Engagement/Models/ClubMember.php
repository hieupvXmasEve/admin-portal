<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Models\AuditableModel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubMember extends AuditableModel
{
    use HasFactory;

    protected $table = 'club_members';

    protected $fillable = [
        'club_id',
        'student_id',
        'role',
        'status',
        'application_notes',
        'approved_by',
        'responsibilities',
        'participation_score',
        'last_active_at',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'responsibilities' => 'array',
        'last_active_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'participation_score' => 'integer',
    ];

    /**
     * Get the club that owns the membership.
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Get the student that owns the membership.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the student who approved this membership.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'approved_by');
    }

    /**
     * Get the role history for this membership.
     */
    public function roleHistory(): HasMany
    {
        return $this->hasMany(ClubMemberRoleHistory::class);
    }

    /**
     * Check if the member is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the member is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the member is the president.
     */
    public function isPresident(): bool
    {
        return $this->role === 'president' && $this->isActive();
    }

    /**
     * Check if the member is an officer.
     */
    public function isOfficer(): bool
    {
        return in_array($this->role, ['president', 'vice_president', 'secretary', 'treasurer']) && $this->isActive();
    }

    /**
     * Scope to get active members.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get pending applications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get members by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get officers.
     */
    public function scopeOfficers($query)
    {
        return $query->whereIn('role', ['president', 'vice_president', 'secretary', 'treasurer']);
    }

    /**
     * Get identifier for logging.
     */
    protected function getIdentifierForLog(): string
    {
        $studentName = $this->student?->full_name ?? 'Unknown Student';
        $clubName = $this->club?->name ?? 'Unknown Club';

        return "{$studentName} in {$clubName}";
    }

    /**
     * Get custom log properties.
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'club_id' => $this->club_id,
            'student_id' => $this->student_id,
            'membership_role' => $this->role,
            'membership_status' => $this->status,
            'participation_score' => $this->participation_score,
        ];
    }

    /**
     * Get campus ID for logging context.
     */
    protected function getCampusIdForLogging(): ?int
    {
        return $this->club?->campus_id ?? parent::getCampusIdForLogging();
    }
}
