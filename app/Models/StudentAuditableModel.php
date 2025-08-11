<?php

namespace App\Models;

use App\Support\CampusLogContext;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class StudentAuditableModel extends Authenticatable
{
    use LogsActivity;

    /**
     * Get the logging level for this model
     */
    protected function getLoggingLevel(): string
    {
        return 'comprehensive'; // Students require comprehensive logging
    }

    /**
     * Get standard fields for logging (key business fields + status)
     */
    protected function getStandardLogFields(): array
    {
        return [
            'student_id',
            'full_name',
            'email',
            'phone',
            'address',
            'campus_id',
            'program_id',
            'specialization_id',
            'curriculum_version_id',
            'status',
            'academic_status',
            'status_change_date',
            'status_reason',
            'status_changed_by',
        ];
    }

    /**
     * Get comprehensive fields for logging
     */
    protected function getComprehensiveLogFields(): array
    {
        return [
            'student_id',
            'full_name',
            'email',
            'phone',
            'address',
            'date_of_birth',
            'gender',
            'nationality',
            'national_id',
            'campus_id',
            'program_id',
            'specialization_id',
            'curriculum_version_id',
            'admission_date',
            'expected_graduation_date',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
            'high_school_name',
            'high_school_graduation_year',
            'entrance_exam_score',
            'status',
            'academic_status',
            'status_change_date',
            'status_reason',
            'status_changed_by',
        ];
    }

    /**
     * Get fields to exclude from logging
     */
    protected function getExcludedLogFields(): array
    {
        return [
            'remember_token',
            'oauth_provider_id',
            'admission_notes', // May contain sensitive information
        ];
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        $loggedFields = array_diff($this->getComprehensiveLogFields(), $this->getExcludedLogFields());

        return LogOptions::defaults()
            ->logOnly($loggedFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getLogName());
    }

    /**
     * Get log name with campus context - ALWAYS campus-aware
     */
    protected function getLogName(): string
    {
        $modelName = 'Student';

        // Get campus ID from model field or session
        $campusId = $this->campus_id ?? CampusLogContext::getCurrentCampusId();

        return CampusLogContext::getLogName($modelName, $campusId);
    }

    /**
     * Get custom activity description
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Enrolled new student: {$identifier}",
            'updated' => "Updated student record: {$identifier}",
            'deleted' => "Removed student: {$identifier}",
            'restored' => "Restored student: {$identifier}",
            default => "{$eventName} student: {$identifier}",
        };
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        if (! empty($this->student_id)) {
            return "{$this->student_id} ({$this->full_name})";
        }

        if (! empty($this->full_name)) {
            return $this->full_name;
        }

        if (! empty($this->email)) {
            return $this->email;
        }

        return "ID {$this->getKey()}";
    }

    /**
     * Additional properties to log - Enhanced with comprehensive campus context
     */
    public function getExtraLogProperties(): array
    {
        // Get comprehensive campus context
        $campusId = $this->campus_id ?? CampusLogContext::getCurrentCampusId();
        $enhancedProperties = CampusLogContext::enhanceLogProperties([], $campusId);

        // Add student-specific academic context
        $academicContext = [
            'student_campus_id' => $this->campus_id ?? null,
            'program_id' => $this->program_id ?? null,
            'specialization_id' => $this->specialization_id ?? null,
            'curriculum_version_id' => $this->curriculum_version_id ?? null,
            'student_id' => $this->student_id ?? null,
            'academic_status' => $this->academic_status ?? null,
        ];

        // Add change context for updates
        $changeContext = [];
        if ($this->isDirty() && $this->exists) {
            $changeContext['changed_fields'] = array_keys($this->getDirty());
            $changeContext['change_count'] = count($this->getDirty());

            // Track important status changes
            if ($this->isDirty('status') || $this->isDirty('academic_status')) {
                $changeContext['status_change'] = true;
                $changeContext['old_status'] = $this->getOriginal('status');
                $changeContext['new_status'] = $this->status;
                $changeContext['old_academic_status'] = $this->getOriginal('academic_status');
                $changeContext['new_academic_status'] = $this->academic_status;
            }
        }

        return array_merge($enhancedProperties, $academicContext, $changeContext);
    }

    /**
     * Handle status change events
     */
    protected static function bootStudentAuditableModel()
    {
        static::updating(function ($student) {
            // Log status changes with additional context
            if ($student->isDirty('status') || $student->isDirty('academic_status')) {
                activity()
                    ->performedOn($student)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'old_status' => $student->getOriginal('status'),
                        'new_status' => $student->status,
                        'old_academic_status' => $student->getOriginal('academic_status'),
                        'new_academic_status' => $student->academic_status,
                        'reason' => $student->status_reason,
                        'changed_by' => auth()->user()->name ?? 'System',
                    ])
                    ->log('Student status changed');
            }
        });
    }
}
