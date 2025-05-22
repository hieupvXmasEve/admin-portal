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

    public function hasPermission($permission_code, $campusId)
    {
        return $this->getAllPermissions($campusId)->contains($permission_code);
    }

    public function getAllPermissions($campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');

        // Lấy tất cả role_id của user trong campus hiện tại
        $roleIds = $this->campusRoles()
            ->where('campus_id', $campusId)
            ->pluck('role_id');
        if ($roleIds->isEmpty()) return collect();

        // Lấy tất cả permissions từ các roles (kèm children nếu cần)
        $roles = Role::with('permissions.children')->whereIn('id', $roleIds)->get();

        $permissions = collect();
        foreach ($roles as $role) {
            foreach ($role->permissions as $perm) {
                $permissions->push($perm); // permission cha
                foreach ($perm->children as $child) {
                    $permissions->push($child); // permission con
                }
            }
        }

        // Trả về unique theo code (hoặc id nếu bạn muốn)
        return $permissions->unique('code')->pluck('code')->values();
    }

}
