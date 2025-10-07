<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScholarshipAward extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'scholarship_code',
        'awarded_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'awarded_at' => 'date',
    ];

    /**
     * Get the student that owns the scholarship award.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the scholarship definition for this award.
     */
    public function scholarshipDefinition(): BelongsTo
    {
        return $this->belongsTo(ScholarshipDefinition::class, 'scholarship_code', 'code');
    }

    /**
     * Get the scholarship definition (alias for better readability).
     */
    public function scholarship(): BelongsTo
    {
        return $this->scholarshipDefinition();
    }

    /**
     * Check if the scholarship is currently valid.
     */
    public function isValid(): bool
    {
        return $this->scholarshipDefinition?->isValid() ?? false;
    }

    /**
     * Check if the scholarship has expired.
     */
    public function isExpired(): bool
    {
        return $this->scholarshipDefinition?->isExpired() ?? true;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($award) {
            // Validate that scholarship code exists
            if (!ScholarshipDefinition::where('code', $award->scholarship_code)->exists()) {
                throw new \InvalidArgumentException('Scholarship code does not exist.');
            }

            // Validate that student exists
            if (!Student::where('id', $award->student_id)->exists()) {
                throw new \InvalidArgumentException('Student does not exist.');
            }

            // Check if student already has a scholarship
            if (static::where('student_id', $award->student_id)->exists()) {
                throw new \InvalidArgumentException('Student already has a scholarship assigned.');
            }
        });

        static::updating(function ($award) {
            // Validate that scholarship code exists
            if (!ScholarshipDefinition::where('code', $award->scholarship_code)->exists()) {
                throw new \InvalidArgumentException('Scholarship code does not exist.');
            }
        });
    }
}
