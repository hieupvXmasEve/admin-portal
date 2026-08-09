<?php

namespace App\Modules\Engagement\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormResultVisibility extends Model
{
    use HasFactory;

    protected $table = 'form_result_visibility';

    protected $fillable = [
        'form_id',
        'role_id',
        'visibility_level',
        'min_aggregation_threshold',
    ];

    protected $casts = [
        'visibility_level' => 'string',
        'min_aggregation_threshold' => 'integer',
    ];

    /**
     * Visibility level constants
     */
    public const VISIBILITY_OWN_SUBMISSION = 'own_submission';

    public const VISIBILITY_AGGREGATED = 'aggregated';

    public const VISIBILITY_FULL_DETAIL = 'full_detail';

    /**
     * Get all possible visibility levels
     */
    public static function getVisibilityLevels(): array
    {
        return [
            self::VISIBILITY_OWN_SUBMISSION => 'Own Submission',
            self::VISIBILITY_AGGREGATED => 'Aggregated Data',
            self::VISIBILITY_FULL_DETAIL => 'Full Details',
        ];
    }

    /**
     * Get the form that owns this visibility setting.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the role that this visibility setting applies to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if this visibility level requires aggregation threshold.
     */
    public function requiresAggregationThreshold(): bool
    {
        return $this->visibility_level === self::VISIBILITY_AGGREGATED;
    }

    /**
     * Check if the visibility level allows seeing individual responses.
     */
    public function allowsIndividualResponses(): bool
    {
        return in_array($this->visibility_level, [
            self::VISIBILITY_OWN_SUBMISSION,
            self::VISIBILITY_FULL_DETAIL,
        ]);
    }

    /**
     * Check if the visibility level allows seeing aggregated data.
     */
    public function allowsAggregatedData(): bool
    {
        return in_array($this->visibility_level, [
            self::VISIBILITY_AGGREGATED,
            self::VISIBILITY_FULL_DETAIL,
        ]);
    }

    /**
     * Get a human-readable description of the visibility level.
     */
    public function getVisibilityDescription(): string
    {
        return match ($this->visibility_level) {
            self::VISIBILITY_OWN_SUBMISSION => 'Can only view their own submissions',
            self::VISIBILITY_AGGREGATED => 'Can view aggregated results when threshold is met',
            self::VISIBILITY_FULL_DETAIL => 'Can view all individual responses and details',
            default => 'Unknown visibility level',
        };
    }

    /**
     * Scope to get settings for a specific form.
     */
    public function scopeForForm($query, int $formId)
    {
        return $query->where('form_id', $formId);
    }

    /**
     * Scope to get settings for a specific role.
     */
    public function scopeForRole($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Scope to get settings by visibility level.
     */
    public function scopeByVisibilityLevel($query, string $level)
    {
        return $query->where('visibility_level', $level);
    }
}
