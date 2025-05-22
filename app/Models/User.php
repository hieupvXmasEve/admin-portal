<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function campusRoles(): HasMany
    {
        return $this->hasMany(CampusUserRole::class);
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class, 'campus_user_roles')->withPivot('role_id')->withTimestamps();
    }

    public function hasPermission($permission, $campusId)
    {
        $campusId = $campusId ?? session('current_campus_id');

        // Lấy Role ID của user tại campus đó
        $roleId = $this->campuses()
            ->where('campus_id', $campusId)
            ->pluck('pivot.role_id')
            ->first();

        if (!$roleId) return false;

        // Kiểm tra xem role đó có permission không
        return Role::where('id', $roleId)
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('code', $permission);
            })->exists();
    }

}
