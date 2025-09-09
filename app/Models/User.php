<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\LazyPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends UserAuditableModel
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, LazyPermissions, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public function getInitialsAttribute()
    {
        return Str::of($this->name)
            ->split('/[\s,]+/')
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->slice(0, 2)
            ->join('');
    }

    public function campusRoles()
    {
        return $this->belongsToMany(Role::class, 'campus_user_roles', 'user_id', 'role_id')
            ->withPivot('campus_id')
            ->withTimestamps();
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class, 'campus_user_roles')->withPivot('role_id')->withTimestamps();
    }

    /**
     * Lấy danh sách vai trò của người dùng tại một campus
     */
    public function rolesAtCampus(int $campusId)
    {
        return $this->belongsToMany(Role::class, 'campus_user_roles')
            ->wherePivot('campus_id', $campusId);
    }

    public function hasRole($roleCode, $campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');

        return $this->campusRoles()->where('campus_id', $campusId)->where('code', $roleCode)->exists();
    }

    public function getAllPermissions($campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');

        return $this->campusRoles()->where('campus_id', $campusId)->get();
    }

    /**
     * Check if the user is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the user is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Check if the user is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the user is banned
     */
    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    /**
     * Check if the user is locked
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Check if the user is verified
     */
    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /**
     * Check if the user is unverified
     */
    public function isUnverified(): bool
    {
        return $this->status === self::STATUS_UNVERIFIED;
    }

    /**
     * Activate the user
     */
    public function activate(): bool
    {
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Deactivate the user
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => self::STATUS_INACTIVE]);
    }

    /**
     * Suspend the user
     */
    public function suspend(): bool
    {
        return $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Ban the user
     */
    public function ban(): bool
    {
        return $this->update(['status' => self::STATUS_BANNED]);
    }

    /**
     * Lock the user account
     */
    public function lock(): bool
    {
        return $this->update(['status' => self::STATUS_LOCKED]);
    }

    /**
     * Mark user as verified
     */
    public function markAsVerified(): bool
    {
        return $this->update(['status' => self::STATUS_VERIFIED]);
    }

    /**
     * Mark user as unverified
     */
    public function markAsUnverified(): bool
    {
        return $this->update(['status' => self::STATUS_UNVERIFIED]);
    }

    public function hasSystemRole(string $roleCode): bool
    {
        return $this->campusRoles()->where('code', $roleCode)->exists();
    }

    /**
     * Email preferences relationship
     */
    public function emailPreferences()
    {
        return $this->hasMany(UserEmailPreference::class);
    }

    public function children()
    {
        return $this->hasMany(Student::class, 'parent_user_id');
    }

    /**
     * Email logs relationship
     */
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Upload records relationship
     */
    public function uploadRecords()
    {
        return $this->hasMany(UploadRecord::class);
    }

    /**
     * Available user status constants
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BANNED = 'banned';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_UNVERIFIED = 'unverified';

    /**
     * Get available status options
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_BANNED => 'Banned',
            self::STATUS_LOCKED => 'Locked',
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_UNVERIFIED => 'Unverified',
        ];
    }

    /**
     * Get all available status values
     */
    public static function getStatusValues(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_PENDING,
            self::STATUS_SUSPENDED,
            self::STATUS_BANNED,
            self::STATUS_LOCKED,
            self::STATUS_VERIFIED,
            self::STATUS_UNVERIFIED,
        ];
    }
}
