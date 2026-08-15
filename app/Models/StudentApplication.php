<?php

namespace App\Models;

use App\Modules\Admissions\Models\ApplicationAcademicScore;
use App\Modules\Upload\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentApplication extends AuditableModel
{
    use HasFactory;

    /**
     * Application lifecycle statuses (BE-validated allow-list, not a DB enum).
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ENROLLED,
            self::STATUS_REJECTED,
        ];
    }

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
        'study_link_status',
        'english_qualifications',
        'sut_id',
        'is_international_applicant',
        'exception_units',
        'status',
        'student_id',
        'student_code',
        'crm_admission_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_reason',
        'revoked_by',
        'revoked_at',
        'crm_campus',
        'crm_major',
        'province',
        'new_province',
        'new_street',
        'new_ward',
        'permanent_address',
        'birth_place',
        'nationality',
        'religion',
        'id_card_place_of_issue',
        'school',
        'graduation_year',
        'gpa',
        'gpa_type',
        'scholarship',
        'pathway_gateway',
        'uu_dai_gc',
        'crm_paid_amount',
        'registration_form',
        'last_synced_at',
    ];

    protected $casts = [
        'registration_form' => 'boolean',
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
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'gpa' => 'decimal:2',
        'crm_paid_amount' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_international_applicant' => false,
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the student that was created from this application
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Guardians (parents / responsible adults) on this Application (1-n).
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(ApplicationGuardian::class);
    }

    /**
     * Documents on this Application (1-n external link references). Multiple
     * documents may share a `file_type_code` (e.g. a multi-page transcript).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Per-subject academic scores (school report + national exam), from the
     * CRM sync. Absence of a row means "not reported".
     */
    public function academicScores(): HasMany
    {
        return $this->hasMany(ApplicationAcademicScore::class, 'student_application_id');
    }

    /**
     * The primary Guardian, if one has been recorded. Exactly one Guardian is
     * primary whenever any exist (DB + service invariant), so this is the
     * Guardian that maps to the Student's emergency contact on approval.
     */
    public function primaryGuardian(): ?ApplicationGuardian
    {
        return $this->guardians()->where('is_primary', true)->first();
    }

    /**
     * Staff member who approved this application.
     *
     * Named `approvedByUser` (not `approvedBy`) so the serialized relation key
     * (`approved_by_user`) does not collide with the `approved_by` FK column.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Staff member who rejected this application.
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Staff member who revoked a mistaken approval of this application.
     */
    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isEnrolled(): bool
    {
        return $this->status === self::STATUS_ENROLLED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
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
            'crm_admission_id',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'revoked_by',
            'revoked_at',
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

    /**
     * Get campus relationship from campus_code
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_code', 'code');
    }
}
