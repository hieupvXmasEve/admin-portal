<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Club extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'name',
        'description',
        'founded_date',
        'avatar_url',
        'thumbnail_url',
        'cover_url',
        'social_links',
        'contact_email',
        'contact_phone',
        'status',
        'achievements',
    ];

    protected $casts = [
        'social_links' => 'array',
        'achievements' => 'array',
        'founded_date' => 'date:Y-m-d',
    ];

    /**
     * Get the campus that owns the club.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get all members of the club.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    /**
     * Get the active members of the club.
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(ClubMember::class)->where('status', 'active');
    }

    /**
     * Get the pending membership applications.
     */
    public function pendingApplications(): HasMany
    {
        return $this->hasMany(ClubMember::class)->where('status', 'pending');
    }

    /**
     * Get the president of the club.
     */
    public function president(): HasOne
    {
        return $this->hasOne(ClubMember::class)
            ->where('role', 'president')
            ->where('status', 'active');
    }

    /**
     * Get the officers of the club (president, vice_president, secretary, treasurer).
     */
    public function officers(): HasMany
    {
        return $this->hasMany(ClubMember::class)
            ->whereIn('role', ['president', 'vice_president', 'secretary', 'treasurer'])
            ->where('status', 'active');
    }

    /**
     * Get the member count for the club.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->activeMembers()->count();
    }

    /**
     * Check if the club is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope to get active clubs.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get clubs for a specific campus.
     */
    public function scopeForCampus($query, int $campusId)
    {
        return $query->where('campus_id', $campusId);
    }

    /**
     * Get identifier for logging.
     */
    protected function getIdentifierForLog(): string
    {
        return $this->name ?? "ID {$this->getKey()}";
    }

    /**
     * Get custom log properties.
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'club_status' => $this->status,
            'member_count' => $this->member_count ?? 0,
        ];
    }
}
