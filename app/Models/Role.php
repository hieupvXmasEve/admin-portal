<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends AuditableModel
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
            $code = $originalCode.'_'.$counter;
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

    /**
     * Configure comprehensive logging for roles (security critical)
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
        return ['name', 'code'];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        return $this->name ?? $this->code ?? "Role ID {$this->getKey()}";
    }

    /**
     * Custom activity descriptions for role events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created role: {$identifier}",
            'updated' => "Updated role: {$identifier}",
            'deleted' => "Deleted role: {$identifier}",
            'restored' => "Restored role: {$identifier}",
            default => "{$eventName} role: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'role_code' => $this->code,
            'permissions_count' => $this->permissions()->count(),
            'users_count' => $this->users()->count(),
        ];
    }
}
