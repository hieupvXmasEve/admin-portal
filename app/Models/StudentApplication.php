<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentApplication extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'gender',
        'ethnicity',
        'birth_day',
        'birth_month',
        'birth_year',
        'national_id',
        'phone',
        'email',
        'address',
        'health_information',
        'parent_phone',
        'parent_email',
        'campus_code',
        'intended_program',
        'intended_specialization',
        'intake',
        'exam_date',
        'english_test_type',
        'listening',
        'reading',
        'writing',
        'speaking',
        'overall',
        'submitted_photo',
        'submitted_cccd',
        'submitted_ccta',
        'submitted_tn_translate',
        'submitted_hb_translate',
        'submitted_other',
        'submitted_insurance_card',
        'submitted_exemption_gc',
        'study_link_status',
        'english_qualifications',
        'sut_id',
        'is_international_applicant',
        'exception_units',
        'status',
        'student_id',
    ];

    protected $casts = [
        'birth_day' => 'integer',
        'birth_month' => 'integer',
        'birth_year' => 'integer',
        'exam_date' => 'date',
        'listening' => 'decimal:2',
        'reading' => 'decimal:2',
        'writing' => 'decimal:2',
        'speaking' => 'decimal:2',
        'overall' => 'decimal:2',
        'is_international_applicant' => 'boolean',
        'status' => 'string',
    ];

    /**
     * Get the student that was created from this application
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Check if this application has been converted to a student
     */
    public function isConverted(): bool
    {
        return ! is_null($this->student_id);
    }

    /**
     * Scope to get only unconverted applications
     */
    public function scopeUnconverted($query)
    {
        return $query->whereNull('student_id');
    }

    /**
     * Scope to get only converted applications
     */
    public function scopeConverted($query)
    {
        return $query->whereNotNull('student_id');
    }

    /**
     * Configure comprehensive logging for student applications
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get standard fields for logging (excluding sensitive data)
     */
    protected function getStandardLogFields(): array
    {
        return [
            'full_name', 'gender', 'ethnicity', 'phone', 'email',
            'campus_code', 'intended_program', 'intended_specialization', 'intake',
            'exam_date', 'english_test_type', 'overall',
            'study_link_status', 'is_international_applicant',
            'status', 'student_id',
        ];
    }

    /**
     * Get fields to exclude from logging
     */
    protected function getExcludedLogFields(): array
    {
        return [
            'national_id', // Sensitive personal information
            'address', // Personal address
            'parent_phone', 'parent_email', // Parent contact info
            'health_information', // Medical information
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        if (! empty($this->full_name)) {
            $campus = $this->campus_code ? " ({$this->campus_code})" : '';

            return $this->full_name.$campus;
        }

        if (! empty($this->email)) {
            return $this->email;
        }

        if (! empty($this->sut_id)) {
            return "SUT ID: {$this->sut_id}";
        }

        return "Application ID {$this->getKey()}";
    }

    /**
     * Custom activity descriptions for student application events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "New student application submitted: {$identifier}",
            'updated' => "Updated student application: {$identifier}",
            'deleted' => "Deleted student application: {$identifier}",
            'restored' => "Restored student application: {$identifier}",
            default => "{$eventName} student application: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'campus_code' => $this->campus_code,
            'intended_program' => $this->intended_program,
            'status' => $this->status,
            'is_converted' => $this->isConverted(),
            'is_international' => $this->is_international_applicant,
        ];
    }
}
