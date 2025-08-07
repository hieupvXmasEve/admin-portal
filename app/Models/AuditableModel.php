<?php

namespace App\Models;

use App\Support\CampusLogContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

abstract class AuditableModel extends Model
{
    use LogsActivity;

    /**
     * Logging level constants
     */
    public const LOG_LEVEL_MINIMAL = 'minimal';
    public const LOG_LEVEL_STANDARD = 'standard';
    public const LOG_LEVEL_COMPREHENSIVE = 'comprehensive';

    /**
     * Get the logging level for this model
     * Override this method in child classes to customize
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    /**
     * Get fields to log based on logging level
     * Override this method in child classes for custom field selection
     */
    protected function getLoggedFields(): array
    {
        $level = $this->getLoggingLevel();

        return match ($level) {
            static::LOG_LEVEL_MINIMAL => $this->getMinimalLogFields(),
            static::LOG_LEVEL_STANDARD => $this->getStandardLogFields(),
            static::LOG_LEVEL_COMPREHENSIVE => $this->getComprehensiveLogFields(),
            default => $this->getStandardLogFields(),
        };
    }

    /**
     * Get minimal fields for logging (status/critical changes only)
     * Override in child classes
     */
    protected function getMinimalLogFields(): array
    {
        return ['status'];
    }

    /**
     * Get standard fields for logging (key business fields + status)
     * Override in child classes
     */
    protected function getStandardLogFields(): array
    {
        return $this->fillable;
    }

    /**
     * Get comprehensive fields for logging (all fillable fields)
     * Override in child classes
     */
    protected function getComprehensiveLogFields(): array
    {
        return $this->fillable;
    }

    /**
     * Get fields to exclude from logging
     * Override in child classes to exclude sensitive fields
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
        $loggedFields = array_diff($this->getLoggedFields(), $this->getExcludedLogFields());

        $options = LogOptions::defaults()
            ->logOnly($loggedFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getLogName()); // Force campus-aware log name for ALL models

        return $options;
    }

    /**
     * Get log name with campus context - ALWAYS campus-aware
     */
    protected function getLogName(): string
    {
        $modelName = class_basename($this);

        // Get campus ID from model field, session, or fallback
        $campusId = $this->getCampusIdForLogging();

        return CampusLogContext::getLogName($modelName, $campusId);
    }

    /**
     * Get campus ID for logging context
     */
    protected function getCampusIdForLogging(): ?int
    {
        // 1. Try model's campus_id field
        if (isset($this->campus_id)) {
            return $this->campus_id;
        }

        // 2. Try session campus (most common case)
        if (session('current_campus_id')) {
            return (int) session('current_campus_id');
        }

        // 3. Try authenticated user's campus context
        return CampusLogContext::getCurrentCampusId();
    }

    /**
     * Get custom activity description
     * Override in child classes for model-specific descriptions
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
     * Override in child classes to customize identifier
     */
    protected function getIdentifierForLog(): string
    {
        // Try common identifier fields in order of preference
        $identifierFields = ['name', 'title', 'code', 'full_name', 'student_code', 'email'];

        foreach ($identifierFields as $field) {
            if (isset($this->attributes[$field]) && !empty($this->attributes[$field])) {
                return $this->attributes[$field];
            }
        }

        // Fallback to ID
        return "ID {$this->getKey()}";
    }

    /**
     * Additional properties to log - Enhanced with comprehensive campus context
     * Override in child classes to add custom properties
     */
    public function getExtraLogProperties(): array
    {
        // Get comprehensive campus context
        $campusId = $this->getCampusIdForLogging();
        $enhancedProperties = CampusLogContext::enhanceLogProperties([], $campusId);

        // Add model-specific properties
        $modelProperties = $this->getCustomLogProperties();

        // Add change context for updates
        $changeContext = $this->getChangeContext();

        return array_merge($enhancedProperties, $modelProperties, $changeContext);
    }

    /**
     * Get change context for better audit trail
     */
    protected function getChangeContext(): array
    {
        $context = [];

        // Add information about what changed for updates
        if ($this->isDirty() && $this->exists) {
            $context['changed_fields'] = array_keys($this->getDirty());
            $context['change_count'] = count($this->getDirty());
        }

        // Add creation context
        if (!$this->exists) {
            $context['operation'] = 'create';
        } elseif ($this->isDirty()) {
            $context['operation'] = 'update';
        }

        return $context;
    }

    /**
     * Custom properties specific to the model
     * Override in child classes
     */
    protected function getCustomLogProperties(): array
    {
        return [];
    }

    /**
     * Determine if logging should be skipped
     * Override in child classes for conditional logging
     */
    protected function shouldSkipLogging(): bool
    {
        return false;
    }

    /**
     * Boot the auditable model
     */
    protected static function bootAuditableModel()
    {
        // Add any global model event listeners here if needed
    }
}
