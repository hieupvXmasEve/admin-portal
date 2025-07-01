<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = ['name', 'code', 'description', 'parent_id', 'display_name', 'module'];

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Get the parent permission.
     */
    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    /**
     * Get the children permissions.
     */
    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id');
    }

    /**
     * Nhóm quyền theo module
     */
    public static function getGroupedPermissions()
    {
        $permissions = self::all();
        $grouped = [];

        foreach ($permissions as $permission) {
            if (!isset($grouped[$permission->module])) {
                $grouped[$permission->module] = [];
            }

            $grouped[$permission->module][] = $permission;
        }

        return $grouped;
    }
}
