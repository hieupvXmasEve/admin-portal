<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class RolePermission extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\RolePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Configure comprehensive logging for role permissions (security critical)
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
        return ['role_id', 'permission_id'];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $roleName = $this->role?->name ?? "Role ID {$this->role_id}";
        $permissionName = $this->permission?->display_name ?? $this->permission?->name ?? "Permission ID {$this->permission_id}";

        return "{$roleName} -> {$permissionName}";
    }

    /**
     * Custom activity descriptions for role permission events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Granted permission: {$identifier}",
            'updated' => "Updated permission assignment: {$identifier}",
            'deleted' => "Revoked permission: {$identifier}",
            'restored' => "Restored permission: {$identifier}",
            default => "{$eventName} permission assignment: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'role_code' => $this->role?->code,
            'permission_code' => $this->permission?->code,
            'permission_module' => $this->permission?->module,
            'assignment_type' => 'role_permission',
        ];
    }

    /**
     * Override log name for security context
     */
    protected function getLogName(): string
    {
        return 'security_role_permission';
    }
}
