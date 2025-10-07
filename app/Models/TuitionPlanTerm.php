<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuitionPlanTerm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tuition_plan_id',
        'semester_id',
        'term_number',
        'amount',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:0',
        'due_date' => 'date',
    ];

    /**
     * Get the tuition plan that owns the term.
     */
    public function tuitionPlan(): BelongsTo
    {
        return $this->belongsTo(TuitionPlan::class);
    }

    /**
     * Get the semester that owns the term.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Check if this term has a zero amount.
     */
    public function isZeroAmount(): bool
    {
        return $this->amount == 0;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($term) {
            // Validate amount
            if ($term->amount < 0) {
                throw new \InvalidArgumentException('Term amount must be greater than or equal to zero.');
            }

            // Validate term number
            if ($term->term_number < 1) {
                throw new \InvalidArgumentException('Term number must be greater than zero.');
            }
        });

        static::updating(function ($term) {
            // Validate amount
            if ($term->amount < 0) {
                throw new \InvalidArgumentException('Term amount must be greater than or equal to zero.');
            }

            // Validate term number
            if ($term->term_number < 1) {
                throw new \InvalidArgumentException('Term number must be greater than zero.');
            }
        });
    }
}
