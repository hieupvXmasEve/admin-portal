<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = ['name', 'code'];

    protected static function booted()
    {
        static::creating(function ($role) {
            if (empty($role->code)) {
                $role->code = static::generateRoleCode($role->name);
            }
        });

        static::updating(function ($role) {
            if ($role->isDirty('name') && empty($role->code)) {
                $role->code = static::generateRoleCode($role->name);
            }
        });
    }

    public static function generateRoleCode(string $name): string
    {
        // Convert to snake_case and remove special characters
        $code = Str::snake(Str::ascii($name));
        $code = preg_replace('/[^a-z0-9_]/', '', $code);

        // Ensure uniqueness
        $originalCode = $code;
        $counter = 1;

        while (static::where('code', $code)->exists()) {
            $code = $originalCode . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'campus_user_roles', 'role_id', 'user_id')
            ->withPivot('campus_id')
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Kiểm tra vai trò có quyền cụ thể không
     */
    public function hasPermission($permissionName)
    {

        // Fallback: kiểm tra qua relationship
        return $this->permissions()->where('name', $permissionName)->exists();
    }
}
