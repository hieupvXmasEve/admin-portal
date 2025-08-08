<?php

namespace App\Models;

use App\Support\CampusLogContext;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class UserAuditableModel extends Authenticatable
{
    use LogsActivity;

    /**
     * Get the logging level for this model
     */
    protected function getLoggingLevel(): string
    {
        return 'standard';
    }

    /**
     * Get standard fields for logging (key business fields + status)
     */
    protected function getStandardLogFields(): array
    {
        return ['name', 'email', 'phone', 'address', 'status'];
    }

    /**
     * Get fields to exclude from logging
     */
    protected function getExcludedLogFields(): array
    {
        return [
            'password',
            'remember_token',
            'api_token',
            'oauth_provider_id',
        ];
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        $loggedFields = array_diff($this->getStandardLogFields(), $this->getExcludedLogFields());

        return LogOptions::defaults()
            ->logOnly($loggedFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getLogName());
    }

    /**
     * Get log name with campus context - ALWAYS campus-aware
     */
    protected function getLogName(): string
    {
        $modelName = class_basename($this);
        $campusId = CampusLogContext::getCurrentCampusId();

        return CampusLogContext::getLogName($modelName, $campusId);
    }

    /**
     * Get custom activity description
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $modelName = class_basename($this);
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created {$modelName}: {$identifier}",
            'updated' => "Updated {$modelName}: {$identifier}",
            'deleted' => "Deleted {$modelName}: {$identifier}",
            'restored' => "Restored {$modelName}: {$identifier}",
            default => "{$eventName} {$modelName}: {$identifier}",
        };
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        // Try common identifier fields in order of preference
        $identifierFields = ['name', 'email', 'full_name'];

        foreach ($identifierFields as $field) {
            if (isset($this->attributes[$field]) && ! empty($this->attributes[$field])) {
                return $this->attributes[$field];
            }
        }

        // Fallback to ID
        return "ID {$this->getKey()}";
    }

    /**
     * Additional properties to log - Enhanced with comprehensive campus context
     */
    public function getExtraLogProperties(): array
    {
        // Get comprehensive campus context
        $campusId = CampusLogContext::getCurrentCampusId();
        $enhancedProperties = CampusLogContext::enhanceLogProperties([], $campusId);

        // Add user-specific context
        $userContext = [];
        if (auth()->check() && auth()->id() !== $this->getKey()) {
            $userContext['modified_by_user_id'] = auth()->id();
            $userContext['modified_by_email'] = auth()->user()->email;
            $userContext['self_modification'] = false;
        } else {
            $userContext['self_modification'] = true;
        }

        // Add change context for updates
        $changeContext = [];
        if ($this->isDirty() && $this->exists) {
            $changeContext['changed_fields'] = array_keys($this->getDirty());
            $changeContext['change_count'] = count($this->getDirty());
        }

        return array_merge($enhancedProperties, $userContext, $changeContext);
    }
}
