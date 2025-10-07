<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TuitionPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'curriculum_version_id',
        'intake_semester_id',
        'total_amount',
        'currency',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:0',
        'is_active' => 'boolean',
    ];

    /**
     * Get the curriculum version that owns the tuition plan.
     */
    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    /**
     * Get the intake semester that owns the tuition plan.
     */
    public function intakeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_semester_id');
    }

    /**
     * Get the terms for the tuition plan.
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TuitionPlanTerm::class)->orderBy('term_number');
    }

    /**
     * Scope to get only active tuition plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            // Validate curriculum/intake uniqueness
            if (static::where('curriculum_version_id', $plan->curriculum_version_id)
                ->where('intake_semester_id', $plan->intake_semester_id)
                ->exists()) {
                throw new \InvalidArgumentException('A tuition plan already exists for this curriculum version and intake semester combination.');
            }

            // Validate total amount
            if ($plan->total_amount < 0) {
                throw new \InvalidArgumentException('Total amount must be greater than or equal to zero.');
            }
        });

        static::updating(function ($plan) {
            // Validate total amount
            if ($plan->total_amount < 0) {
                throw new \InvalidArgumentException('Total amount must be greater than or equal to zero.');
            }
        });
    }
}
