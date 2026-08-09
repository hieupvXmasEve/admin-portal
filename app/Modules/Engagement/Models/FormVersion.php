<?php

namespace App\Modules\Engagement\Models;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersion extends Model
{
    use HasFactory;

    protected $table = 'form_versions';

    protected $fillable = [
        'form_id',
        'version_no',
        'is_published',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    /**
     * Get the form that owns the version.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the sections for the form version.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('order_index');
    }

    /**
     * Get the questions for the form version.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order_index');
    }

    /**
     * Get the responses for this form version.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /**
     * Scope to get published versions.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to get currently effective versions.
     */
    public function scopeEffective(Builder $query, $datetime = null): Builder
    {
        $datetime = $datetime ?: now();

        return $query->where(function ($q) use ($datetime) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $datetime);
        })->where(function ($q) use ($datetime) {
            $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $datetime);
        });
    }

    /**
     * Check if the version is currently effective.
     */
    public function isEffective($datetime = null): bool
    {
        $datetime = $datetime ?: now();

        $fromValid = ! $this->effective_from || $this->effective_from <= $datetime;
        $toValid = ! $this->effective_to || $this->effective_to >= $datetime;

        return $fromValid && $toValid;
    }
}
