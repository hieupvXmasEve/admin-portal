<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Get the campuses associated with the user.
     *
     * This defines a many-to-many relationship between users and campuses
     * through the 'user_campus_roles' pivot table. The pivot table also
     * contains the 'role_id' field and timestamps for the relationship.
     *
     * @return BelongsToMany
     */
    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class, 'user_campus_roles', 'user_id', 'campus_id')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Get the roles associated with the user.
     *
     * This defines a many-to-many relationship between users and roles
     * through the 'user_campus_roles' pivot table. The pivot table also
     * contains the 'campus_id' field and timestamps for the relationship.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_campus_roles', 'user_id', 'role_id')
            ->withPivot('campus_id')
            ->withTimestamps();
    }

    public function hasPermission($permissionName, $campusId)
    {
        // Lấy Role ID của user tại campus đó
        $roleId = DB::table('user_campus_roles')
            ->where('user_id', $this->id)
            ->where('campus_id', $campusId)
            ->value('role_id');

        if (!$roleId) return false;

        // Kiểm tra xem role đó có permission không
        return DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.permission_name', $permissionName)
            ->exists();
    }

}
