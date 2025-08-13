<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationApplication extends AuditableModel
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'application_date',
        'intended_graduation_date',
        'status',
        'application_type',
        'ceremony_participation',
        'application_fee_paid',
        'requirements_verified',
        'thesis_submitted',
        'final_transcript_ready',
        'processing_notes',
        'approved_by_user_id',
        'approved_date',
        'rejection_reason',
        'ceremony_date',
        'diploma_mailed_date',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'application_date' => 'date',
        'intended_graduation_date' => 'date',
        'ceremony_participation' => 'boolean',
        'application_fee_paid' => 'boolean',
        'requirements_verified' => 'boolean',
        'thesis_submitted' => 'boolean',
        'final_transcript_ready' => 'boolean',
        'approved_date' => 'date',
        'ceremony_date' => 'date',
        'diploma_mailed_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure comprehensive logging for graduation applications (critical milestone)
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'student_id',
            'application_date',
            'intended_graduation_date',
            'status',
            'application_type',
            'ceremony_participation',
            'application_fee_paid',
            'requirements_verified',
            'thesis_submitted',
            'final_transcript_ready',
            'processing_notes',
            'approved_by_user_id',
            'approved_date',
            'rejection_reason',
            'ceremony_date',
            'diploma_mailed_date',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $studentName = $this->student?->full_name ?? "Student ID {$this->student_id}";
        $applicationDate = $this->application_date?->format('Y-m-d') ?? 'N/A';
        
        return "{$studentName} - Applied {$applicationDate}";
    }

    /**
     * Custom activity descriptions for graduation application events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Submitted graduation application: {$identifier}",
            'updated' => "Updated graduation application: {$identifier}",
            'deleted' => "Cancelled graduation application: {$identifier}",
            'restored' => "Restored graduation application: {$identifier}",
            default => "{$eventName} graduation application: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        $properties = [
            'student_name' => $this->student?->full_name,
            'student_email' => $this->student?->email,
            'student_id_number' => $this->student?->student_id,
            'program' => $this->student?->program?->name,
            'specialization' => $this->student?->specialization?->name,
            'application_status' => $this->status,
            'application_type' => $this->application_type,
            'graduation_timeline' => $this->getGraduationTimeline(),
            'requirements_status' => $this->getRequirementsStatus(),
            'ceremony_details' => $this->getCeremonyDetails(),
            'academic_summary' => $this->getAcademicSummary(),
        ];

        // Track status changes (critical for graduation processing)
        if ($this->isDirty('status') && $this->exists) {
            $properties['status_change'] = [
                'from' => $this->getOriginal('status'),
                'to' => $this->status,
                'changed_at' => now()->toDateTimeString(),
                'processing_stage' => $this->getProcessingStage(),
            ];

            // Special handling for approval
            if ($this->status === 'approved') {
                $properties['approval_details'] = [
                    'approved_by' => $this->approvedBy?->name,
                    'approved_date' => $this->approved_date?->toDateTimeString(),
                    'time_to_approval' => $this->calculateProcessingTime(),
                ];
            }

            // Special handling for rejection
            if ($this->status === 'rejected') {
                $properties['rejection_details'] = [
                    'reason' => $this->rejection_reason,
                    'can_reapply' => true,
                ];
            }
        }

        // Track fee payment
        if ($this->isDirty('application_fee_paid') && $this->exists) {
            $properties['fee_payment_change'] = [
                'paid' => $this->application_fee_paid,
                'changed_at' => now()->toDateTimeString(),
            ];
        }

        // Track requirements verification
        if ($this->isDirty('requirements_verified') && $this->exists) {
            $properties['requirements_verification'] = [
                'verified' => $this->requirements_verified,
                'thesis_submitted' => $this->thesis_submitted,
                'transcript_ready' => $this->final_transcript_ready,
                'all_requirements_met' => $this->areAllRequirementsMet(),
            ];
        }

        // Track diploma mailing
        if ($this->isDirty('diploma_mailed_date') && $this->exists) {
            $properties['diploma_mailing'] = [
                'mailed_date' => $this->diploma_mailed_date?->toDateTimeString(),
                'days_after_ceremony' => $this->calculateDaysAfterCeremony(),
            ];
        }

        return $properties;
    }

    /**
     * Get graduation timeline information
     */
    private function getGraduationTimeline(): array
    {
        return [
            'application_date' => $this->application_date?->format('Y-m-d'),
            'intended_graduation_date' => $this->intended_graduation_date?->format('Y-m-d'),
            'ceremony_date' => $this->ceremony_date?->format('Y-m-d'),
            'days_until_graduation' => $this->calculateDaysUntilGraduation(),
            'processing_duration' => $this->calculateProcessingTime(),
        ];
    }

    /**
     * Get requirements status
     */
    private function getRequirementsStatus(): array
    {
        return [
            'requirements_verified' => $this->requirements_verified,
            'thesis_submitted' => $this->thesis_submitted,
            'final_transcript_ready' => $this->final_transcript_ready,
            'application_fee_paid' => $this->application_fee_paid,
            'all_requirements_met' => $this->areAllRequirementsMet(),
            'pending_requirements' => $this->getPendingRequirements(),
        ];
    }

    /**
     * Get ceremony details
     */
    private function getCeremonyDetails(): array
    {
        return [
            'ceremony_participation' => $this->ceremony_participation,
            'ceremony_date' => $this->ceremony_date?->format('Y-m-d'),
            'diploma_mailed_date' => $this->diploma_mailed_date?->format('Y-m-d'),
            'diploma_status' => $this->getDiplomaStatus(),
        ];
    }

    /**
     * Get academic summary for the graduating student
     */
    private function getAcademicSummary(): array
    {
        if (!$this->student) {
            return [];
        }

        $gpa = $this->student->gpaCalculations()->latest()->first();
        $totalCredits = $this->student->academicRecords()
            ->where('status', 'passed')
            ->sum('credit_points');

        return [
            'final_gpa' => $gpa?->cumulative_gpa ?? 0,
            'total_credits_earned' => $totalCredits,
            'academic_standing' => $this->student->academic_status,
            'enrollment_duration' => $this->calculateEnrollmentDuration(),
        ];
    }

    /**
     * Calculate days until graduation
     */
    private function calculateDaysUntilGraduation(): ?int
    {
        if (!$this->intended_graduation_date) {
            return null;
        }

        return now()->diffInDays($this->intended_graduation_date, false);
    }

    /**
     * Calculate processing time in days
     */
    private function calculateProcessingTime(): ?int
    {
        if (!$this->application_date) {
            return null;
        }

        $endDate = $this->approved_date ?? now();
        return $this->application_date->diffInDays($endDate);
    }

    /**
     * Calculate days after ceremony for diploma mailing
     */
    private function calculateDaysAfterCeremony(): ?int
    {
        if (!$this->ceremony_date || !$this->diploma_mailed_date) {
            return null;
        }

        return $this->ceremony_date->diffInDays($this->diploma_mailed_date);
    }

    /**
     * Calculate enrollment duration
     */
    private function calculateEnrollmentDuration(): ?string
    {
        if (!$this->student || !$this->student->admission_date) {
            return null;
        }

        $years = $this->student->admission_date->diffInYears($this->intended_graduation_date ?? now());
        $months = $this->student->admission_date->diffInMonths($this->intended_graduation_date ?? now()) % 12;

        return "{$years} years, {$months} months";
    }

    /**
     * Check if all requirements are met
     */
    private function areAllRequirementsMet(): bool
    {
        return $this->requirements_verified &&
               $this->thesis_submitted &&
               $this->final_transcript_ready &&
               $this->application_fee_paid;
    }

    /**
     * Get list of pending requirements
     */
    private function getPendingRequirements(): array
    {
        $pending = [];

        if (!$this->requirements_verified) {
            $pending[] = 'requirements_verification';
        }
        if (!$this->thesis_submitted) {
            $pending[] = 'thesis_submission';
        }
        if (!$this->final_transcript_ready) {
            $pending[] = 'final_transcript';
        }
        if (!$this->application_fee_paid) {
            $pending[] = 'application_fee';
        }

        return $pending;
    }

    /**
     * Get processing stage
     */
    private function getProcessingStage(): string
    {
        return match ($this->status) {
            'pending' => 'initial_review',
            'under_review' => 'requirements_verification',
            'approved' => 'graduation_approved',
            'rejected' => 'application_rejected',
            'graduated' => 'graduation_completed',
            default => 'unknown',
        };
    }

    /**
     * Get diploma status
     */
    private function getDiplomaStatus(): string
    {
        if ($this->diploma_mailed_date) {
            return 'mailed';
        } elseif ($this->ceremony_date && $this->ceremony_date->isPast()) {
            return 'ready_to_mail';
        } elseif ($this->status === 'approved') {
            return 'pending_ceremony';
        }

        return 'not_applicable';
    }

    /**
     * Override to include campus context from student
     */
    protected function getCampusIdForLogging(): ?int
    {
        return $this->student?->campus_id ?? parent::getCampusIdForLogging();
    }
}
