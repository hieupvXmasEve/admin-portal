<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CampusUserRole extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\CampusUserRoleFactory> */
    use HasFactory;

    protected $table = 'campus_user_roles';

    protected $fillable = ['user_id', 'campus_id', 'role_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Configure comprehensive logging for user role assignments (security critical)
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
        return ['user_id', 'campus_id', 'role_id'];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $userName = $this->user?->name ?? "User ID {$this->user_id}";
        $roleName = $this->role?->name ?? "Role ID {$this->role_id}";
        $campusName = $this->campus?->name ?? "Campus ID {$this->campus_id}";
        
        return "{$userName} -> {$roleName} at {$campusName}";
    }

    /**
     * Custom activity descriptions for campus user role events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Assigned role: {$identifier}",
            'updated' => "Updated role assignment: {$identifier}",
            'deleted' => "Removed role assignment: {$identifier}",
            'restored' => "Restored role assignment: {$identifier}",
            default => "{$eventName} role assignment: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'user_email' => $this->user?->email,
            'role_code' => $this->role?->code,
            'campus_code' => $this->campus?->code,
            'assignment_type' => 'campus_role',
        ];
    }

    /**
     * Override log name to include campus context
     */
    protected function getLogName(): string
    {
        return "user_role_campus_{$this->campus_id}";
    }
}
