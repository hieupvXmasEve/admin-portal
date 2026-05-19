<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipDefinition extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'amount',
        'total_amount',
        'total_terms',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:0',
        'total_amount' => 'decimal:2',
        'total_terms' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the student scholarship awards for this scholarship.
     */
    public function studentScholarshipAwards(): HasMany
    {
        return $this->hasMany(StudentScholarshipAward::class, 'scholarship_code', 'code');
    }

    /**
     * Get the students who have been awarded this scholarship.
     */
    public function students(): HasMany
    {
        return $this->hasMany(StudentScholarshipAward::class, 'scholarship_code', 'code');
    }

    /**
     * Check if the scholarship is currently valid.
     */
    public function isValid(?Carbon $date = null): bool
    {
        $checkDate = $date ?? Carbon::now();

        return $this->is_active
            && $checkDate->greaterThanOrEqualTo($this->valid_from)
            && $checkDate->lessThanOrEqualTo($this->valid_until);
    }

    /**
     * Check if the scholarship has expired.
     */
    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->valid_until);
    }

    /**
     * Get the count of students assigned to this scholarship.
     */
    public function getStudentCountAttribute(): int
    {
        return $this->studentScholarshipAwards()->count();
    }

    /**
     * Scope to get only active scholarships.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get scholarships valid on a specific date.
     */
    public function scopeValidOn($query, Carbon $date)
    {
        return $query->where('valid_from', '<=', $date)
            ->where('valid_until', '>=', $date)
            ->where('is_active', true);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($scholarship) {
            // Validate code uniqueness
            if (static::where('code', $scholarship->code)->exists()) {
                throw new \InvalidArgumentException('Scholarship code must be unique.');
            }

            // Validate date range
            if ($scholarship->valid_from >= $scholarship->valid_until) {
                throw new \InvalidArgumentException('Valid from date must be before valid until date.');
            }

            // Validate amount
            if ($scholarship->amount <= 0) {
                throw new \InvalidArgumentException('Scholarship amount must be greater than zero.');
            }
        });

        static::updating(function ($scholarship) {
            // Validate date range
            if ($scholarship->valid_from >= $scholarship->valid_until) {
                throw new \InvalidArgumentException('Valid from date must be before valid until date.');
            }

            // Validate amount
            if ($scholarship->amount <= 0) {
                throw new \InvalidArgumentException('Scholarship amount must be greater than zero.');
            }
        });
    }
}
