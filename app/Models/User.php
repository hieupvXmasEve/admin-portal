<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\LazyPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, LazyPermissions;

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
}
