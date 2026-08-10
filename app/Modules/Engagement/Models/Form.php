<?php

namespace App\Modules\Engagement\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'forms';

    protected $fillable = [
        'code',
        'type',
        'title',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'type' => 'string',
        'status' => 'string',
        'aggregate_config' => 'array',
    ];

    /**
     * Get the user who created the form.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the versions for the form.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * Get the latest published version.
     */
    public function latestPublishedVersion()
    {
        return $this->hasOne(FormVersion::class)
            ->where('is_published', true)
            ->orderBy('version_no', 'desc');
    }

    /**
     * Get the targets for the form.
     */
    public function targets(): HasMany
    {
        return $this->hasMany(FormTarget::class);
    }

    /**
     * Get the visibility roles for the form.
     */
    public function visibilityRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'form_visibility_roles');
    }

    /**
     * Get the result visibility settings for the form.
     */
    public function resultVisibility(): HasMany
    {
        return $this->hasMany(FormResultVisibility::class);
    }

    /**
     * Get the responses for the form.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /**
     * Scope to get active forms.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get forms by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the form is available for a campus at a given time.
     */
    public function isAvailableForCampus($campusId, $datetime = null)
    {
        $datetime = $datetime ?: now();

        return $this->targets()
            ->where(function ($query) use ($campusId) {
                $query->whereNull('campus_id')
                    ->orWhere('campus_id', $campusId);
            })
            ->where('start_at', '<=', $datetime)
            ->where(function ($query) use ($datetime) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $datetime);
            })
            ->exists();
    }
}
