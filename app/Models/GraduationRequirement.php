<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationRequirement extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'specialization_id',
        'total_credits_required',
        'core_credits_required',
        'major_credits_required',
        'elective_credits_required',
        'minimum_gpa',
        'minimum_major_gpa',
        'maximum_study_years',
        'required_internship',
        'required_thesis',
        'required_english_certification',
        'special_requirements',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'total_credits_required' => 'decimal:2',
        'core_credits_required' => 'decimal:2',
        'major_credits_required' => 'decimal:2',
        'elective_credits_required' => 'decimal:2',
        'minimum_gpa' => 'decimal:2',
        'minimum_major_gpa' => 'decimal:2',
        'required_internship' => 'boolean',
        'required_thesis' => 'boolean',
        'required_english_certification' => 'boolean',
        'special_requirements' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'program_id' => ['required', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'total_credits_required' => ['required', 'numeric', 'min:0', 'max:300'],
            'core_credits_required' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'major_credits_required' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'elective_credits_required' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'minimum_gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'minimum_major_gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'maximum_study_years' => ['nullable', 'integer', 'min:1', 'max:10'],
            'required_internship' => ['nullable', 'boolean'],
            'required_thesis' => ['nullable', 'boolean'],
            'required_english_certification' => ['nullable', 'boolean'],
            'special_requirements' => ['nullable', 'array'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'program_id.required' => 'Program is required',
            'program_id.exists' => 'Selected program does not exist',
            'total_credits_required.required' => 'Total credits required is required',
            'total_credits_required.numeric' => 'Total credits must be a number',
            'effective_from.required' => 'Effective from date is required',
            'effective_to.after' => 'Effective to date must be after effective from date',
        ];
    }

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    // Helper Methods
    public function isCurrentlyActive(): bool
    {
        $now = now()->toDateString();

        return $this->is_active &&
               $this->effective_from <= $now &&
               ($this->effective_to === null || $this->effective_to >= $now);
    }

    public function isEffectiveForDate(string $date): bool
    {
        return $this->effective_from <= $date &&
               ($this->effective_to === null || $this->effective_to >= $date);
    }

    public function getTotalRequiredCredits(): float
    {
        return (float) $this->total_credits_required;
    }

    public function getBreakdownCredits(): array
    {
        return [
            'core' => (float) $this->core_credits_required,
            'major' => (float) $this->major_credits_required,
            'elective' => (float) $this->elective_credits_required,
            'total' => (float) $this->total_credits_required,
        ];
    }

    public function getGpaRequirements(): array
    {
        return [
            'overall' => (float) $this->minimum_gpa,
            'major' => (float) $this->minimum_major_gpa,
        ];
    }

    public function getOtherRequirements(): array
    {
        return [
            'internship' => $this->required_internship,
            'thesis' => $this->required_thesis,
            'english_certification' => $this->required_english_certification,
            'maximum_study_years' => $this->maximum_study_years,
            'special' => $this->special_requirements ?? [],
        ];
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeCurrentlyEffective(Builder $query): void
    {
        $now = now()->toDateString();
        $query->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $now);
            });
    }

    public function scopeForProgram(Builder $query, int $programId): void
    {
        $query->where('program_id', $programId);
    }

    public function scopeForSpecialization(Builder $query, ?int $specializationId = null): void
    {
        if ($specializationId) {
            $query->where('specialization_id', $specializationId);
        } else {
            $query->whereNull('specialization_id');
        }
    }

    public function scopeEffectiveForDate(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure standard logging for static requirements
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return [
            'program_id',
            'specialization_id',
            'total_credits_required',
            'minimum_gpa',
            'required_internship',
            'required_thesis',
            'required_english_certification',
            'effective_from',
            'effective_to',
            'is_active',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $programName = $this->program?->name ?? "Program ID {$this->program_id}";
        $specializationName = $this->specialization?->name ?? 'General';
        
        return "{$programName} - {$specializationName}";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created graduation requirements: {$identifier}",
            'updated' => "Updated graduation requirements: {$identifier}",
            'deleted' => "Archived graduation requirements: {$identifier}",
            'restored' => "Restored graduation requirements: {$identifier}",
            default => "{$eventName} graduation requirements: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'program_name' => $this->program?->name,
            'specialization_name' => $this->specialization?->name ?? 'General Program',
            'credit_requirements' => $this->getBreakdownCredits(),
            'gpa_requirements' => $this->getGpaRequirements(),
            'other_requirements' => $this->getOtherRequirements(),
            'effective_period' => [
                'from' => $this->effective_from?->format('Y-m-d'),
                'to' => $this->effective_to?->format('Y-m-d'),
            ],
            'is_currently_active' => $this->isCurrentlyActive(),
        ];

        // Track activation changes
        if ($this->isDirty('is_active') && $this->exists) {
            $properties['activation_change'] = [
                'from' => $this->getOriginal('is_active') ? 'active' : 'inactive',
                'to' => $this->is_active ? 'active' : 'inactive',
                'timestamp' => now()->toDateTimeString(),
            ];
        }

        // Track credit requirement changes
        if ($this->isDirty('total_credits_required') && $this->exists) {
            $properties['credit_change'] = [
                'from' => $this->getOriginal('total_credits_required'),
                'to' => $this->total_credits_required,
                'impact' => 'affects_graduation_eligibility',
            ];
        }

        // Track GPA requirement changes
        if ($this->isDirty('minimum_gpa') && $this->exists) {
            $properties['gpa_change'] = [
                'from' => $this->getOriginal('minimum_gpa'),
                'to' => $this->minimum_gpa,
                'impact' => 'affects_graduation_eligibility',
            ];
        }

        return $properties;
    }
}
