<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'form_version_id',
        'campus_id',
        'scope_type',
        'scope_id',
        'start_at',
        'end_at',
        'submission_limit_per_user',
        'semester_id',
        'status',
        'is_mandatory',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'scope_type' => 'string',
        'is_mandatory' => 'boolean',
        'semester_id' => 'string', // Assuming string based on migration
    ];

    /**
     * Get the form that owns the target.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the form version for the target.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the campus for the target.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get the actual target object (Course, Semester, Department, etc.)
     */
    public function scope(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('scope', 'scope_type', 'scope_id');
    }

    /**
     * Get the student assignments for this target.
     */
    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentFormAssignment::class, 'form_target_id');
    }

    /**
     * Check if the target is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();
        return $this->start_at <= $now && (!$this->end_at || $this->end_at >= $now);
    }



    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }
}