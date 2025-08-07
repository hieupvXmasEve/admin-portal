<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends AuditableModel
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

    /**
     * Configure comprehensive logging for permissions (security critical)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return ['name', 'code', 'description', 'parent_id', 'display_name', 'module'];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        return $this->display_name ?? $this->name ?? $this->code ?? "Permission ID {$this->getKey()}";
    }

    /**
     * Custom activity descriptions for permission events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created permission: {$identifier}",
            'updated' => "Updated permission: {$identifier}",
            'deleted' => "Deleted permission: {$identifier}",
            'restored' => "Restored permission: {$identifier}",
            default => "{$eventName} permission: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'permission_code' => $this->code,
            'module' => $this->module,
            'has_parent' => !is_null($this->parent_id),
            'children_count' => $this->children()->count(),
            'roles_count' => $this->roles()->count(),
        ];
    }
}
