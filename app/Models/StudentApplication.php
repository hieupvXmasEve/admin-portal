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
        'student_code',
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
        'submitted_photo' => 'string',
        'submitted_cccd' => 'string',
        'submitted_ccta' => 'string',
        'submitted_tn_translate' => 'string',
        'submitted_hb_translate' => 'string',
        'submitted_other' => 'string',
        'submitted_insurance_card' => 'string',
        'submitted_exemption_gc' => 'string',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_international_applicant' => false,
        'status' => 'approved',
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
            'full_name',
            'gender',
            'ethnicity',
            'phone',
            'email',
            'campus_code',
            'intended_program',
            'intended_specialization',
            'intake',
            'exam_date',
            'english_test_type',
            'overall',
            'study_link_status',
            'is_international_applicant',
            'status',
            'student_id',
            'student_code',
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
            'parent_phone',
            'parent_email', // Parent contact info
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

            return $this->full_name . $campus;
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

    /**
     * Get campus relationship from campus_code
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_code', 'code');
    }

    /**
     * Resolve campus ID from campus code
     */
    public function resolveCampusId(): ?int
    {
        if (empty($this->campus_code)) {
            return null;
        }

        $campus = Campus::where('code', $this->campus_code)->first();
        return $campus?->id;
    }

    /**
     * Resolve program ID from intended program using mapping
     */
    public function resolveProgramId(): ?int
    {
        if (empty($this->intended_program)) {
            return null;
        }

        $mappingService = app(\App\Services\ProgramMappingService::class);
        return $mappingService->getProgramIdFromIntendedCode($this->intended_program);
    }

    /**
     * Resolve curriculum version ID from intake and program
     */
    public function resolveCurriculumVersionId(): ?int
    {
        if (empty($this->intake)) {
            return null;
        }

        $programId = $this->resolveProgramId();
        if (!$programId) {
            return null;
        }

        $mappingService = app(\App\Services\ProgramMappingService::class);
        return $mappingService->getCurriculumVersionId($this->intake, $programId);
    }

    /**
     * Get complete mapping data for student conversion
     */
    public function getConversionMappingData(): array
    {
        $mappingService = app(\App\Services\ProgramMappingService::class);

        return $mappingService->resolveApplicationMappingData([
            'campus_code' => $this->campus_code,
            'intended_program' => $this->intended_program,
            'intake' => $this->intake,
        ]);
    }

    /**
     * Check if application data is complete for conversion
     */
    public function isReadyForConversion(): bool
    {
        \Log::info("Checking conversion readiness for application {$this->id}", [
            'application_id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'campus_code' => $this->campus_code,
            'student_code' => $this->student_code,
            'intended_program' => $this->intended_program,
            'intake' => $this->intake,
        ]);

        // Allow already converted applications for updates
        if ($this->isConverted()) {
            \Log::info("Application {$this->id} already converted (student_id: {$this->student_id}) - allowing for updates");
            // Continue checking other requirements instead of returning false
        }

        // Required basic data including student_code
        $requiredFields = [
            'full_name' => $this->full_name,
            'email' => $this->email,
            'campus_code' => $this->campus_code,
            'student_code' => $this->student_code,
        ];

        foreach ($requiredFields as $field => $value) {
            if (empty($value)) {
                \Log::warning("Application {$this->id} missing required field: {$field}");
                return false;
            }
        }

        // Check if mapping data can be resolved
        \Log::info("Getting conversion mapping data for application {$this->id}");
        $mappingData = $this->getConversionMappingData();

        \Log::info("Mapping data resolved for application {$this->id}", [
            'mapping_data' => $mappingData,
            'campus_id_resolved' => !empty($mappingData['campus_id']),
            'program_id_resolved' => !empty($mappingData['program_id']),
            'curriculum_version_id_resolved' => !empty($mappingData['curriculum_version_id']),
        ]);

        $isReady = !empty($mappingData['campus_id']) &&
            !empty($mappingData['program_id']) &&
            !empty($mappingData['curriculum_version_id']);

        \Log::info("Application {$this->id} readiness result: " . ($isReady ? 'READY' : 'NOT READY'));

        return $isReady;
    }

    /**
     * Get validation errors for conversion readiness
     */
    public function getConversionValidationErrors(): array
    {
        $errors = [];

        // if ($this->isConverted()) {
        //     $errors[] = 'Application has already been converted';
        //     return $errors;
        // }

        if (empty($this->full_name)) {
            $errors[] = 'Full name is required';
        }

        if (empty($this->email)) {
            $errors[] = 'Email is required';
        }

        if (empty($this->campus_code)) {
            $errors[] = 'Campus code is required';
        }

        if (empty($this->student_code)) {
            $errors[] = 'Student code is required';
        }

        if (empty($this->intended_program)) {
            $errors[] = 'Intended program is required';
        }

        if (empty($this->intake)) {
            $errors[] = 'Intake is required';
        }

        // Check mapping resolution
        if (!empty($this->campus_code) && !$this->resolveCampusId()) {
            $errors[] = "Campus not found for code: {$this->campus_code}";
        }

        if (!empty($this->intended_program) && !$this->resolveProgramId()) {
            $errors[] = "Program not found for intended program: {$this->intended_program}";
        }

        if (!empty($this->intake) && !empty($this->intended_program) && !$this->resolveCurriculumVersionId()) {
            $errors[] = "Curriculum version not found for intake: {$this->intake}";
        }

        return $errors;
    }
}
