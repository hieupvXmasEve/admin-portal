<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentActionType;
use App\Modules\Upload\Models\UploadRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'action_type',
        'reason',
        'notes',
        'signed_at',
        'decision_number',
        'decision_signed_at',
        'decision_signer',
        'decision_id',
        'missing_documents',
        'changed_by_user_id',
        'from_semester_id',
        'return_semester_id',
        'egc_defer_from_block_number',
        'intended_intake_semester_id',
        'dropout_semester_id',
        'effective_semester_id',
        'from_campus_id',
        'to_campus_id',
        'effective_at',
        'previous_status',
        'new_status',
        'previous_campus_id',
    ];

    protected $casts = [
        'action_type' => StudentActionType::class,
        'signed_at' => 'date',
        'decision_signed_at' => 'date',
        'missing_documents' => 'boolean',
        'effective_at' => 'datetime',
        'egc_defer_from_block_number' => 'integer',
    ];

    protected $with = ['changedBy'];

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function fromSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'from_semester_id');
    }

    public function returnSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'return_semester_id');
    }

    public function intendedIntakeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intended_intake_semester_id');
    }

    public function dropoutSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'dropout_semester_id');
    }

    public function effectiveSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'effective_semester_id');
    }

    public function fromCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'from_campus_id');
    }

    public function toCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'to_campus_id');
    }

    public function attachmentRecords(): HasMany
    {
        return $this->hasMany(StudentActionAttachment::class);
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(
            UploadRecord::class,
            'student_action_attachments',
            'student_action_log_id',
            'upload_record_id'
        )->withTimestamps();
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(StudentDecision::class, 'decision_id');
    }

    /**
     * Get the defer case linked to this action log.
     * Only applicable for ACADEMIC_DEFER action type.
     */
    public function deferCase(): HasOne
    {
        return $this->hasOne(DeferCase::class, 'student_action_log_id');
    }

    // =====================
    // Scopes
    // =====================

    public function scopeByActionType($query, string|StudentActionType $actionType)
    {
        $type = $actionType instanceof StudentActionType ? $actionType->value : $actionType;

        return $query->where('action_type', $type);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    public function scopeBySignedDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('signed_at', '>=', $from);
        }
        if ($to) {
            $query->where('signed_at', '<=', $to);
        }

        return $query;
    }

    public function scopeByActor($query, int $userId)
    {
        return $query->where('changed_by_user_id', $userId);
    }

    public function scopeMissingDocuments($query, bool $missing = true)
    {
        return $query->where('missing_documents', $missing);
    }

    public function scopeByCampus($query, int $campusId)
    {
        return $query->where('to_campus_id', $campusId)
            ->orWhere('from_campus_id', $campusId);
    }

    public function scopeByToCampus($query, int $campusId)
    {
        return $query->where('to_campus_id', $campusId);
    }

    // =====================
    // Accessors
    // =====================

    public function getActionTypeLabelAttribute(): string
    {
        return $this->action_type->label();
    }

    public function getActionTypeLabelEnAttribute(): string
    {
        return $this->action_type->labelEn();
    }

    public function getFormattedChangedAtAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    public function getFormattedSignedAtAttribute(): ?string
    {
        return $this->signed_at?->format('d/m/Y');
    }

    public function getFormattedEffectiveAtAttribute(): ?string
    {
        return $this->effective_at?->format('d/m/Y H:i');
    }

    /**
     * Get relevant semester based on action type.
     */
    public function getRelevantSemester(): ?Semester
    {
        return match ($this->action_type) {
            StudentActionType::STUDENT_ENROLLMENT_NE => $this->fromSemester,
            StudentActionType::STUDENT_MAJOR_ENROLLMENT => $this->fromSemester,
            StudentActionType::ACADEMIC_DEFER => $this->fromSemester,
            StudentActionType::ACADEMIC_RESUME => $this->returnSemester,
            StudentActionType::WAITING_COURSE_OPENING => $this->fromSemester,
            StudentActionType::ADMISSION_DEFERRAL => $this->intendedIntakeSemester,
            StudentActionType::ACADEMIC_DROPOUT => $this->dropoutSemester,
            StudentActionType::CAMPUS_TRANSFER => $this->effectiveSemester,
        };
    }

    /**
     * Get a summary of the action for display.
     */
    public function getSummaryAttribute(): string
    {
        return match ($this->action_type) {
            StudentActionType::STUDENT_ENROLLMENT_NE => sprintf(
                'NE enrollment in %s',
                $this->fromSemester?->name ?? 'N/A'
            ),
            StudentActionType::STUDENT_MAJOR_ENROLLMENT => sprintf(
                'Major enrollment in %s',
                $this->fromSemester?->name ?? 'N/A'
            ),
            StudentActionType::ACADEMIC_DEFER => sprintf(
                'Defer from %s to %s%s',
                $this->fromSemester?->name ?? 'N/A',
                $this->returnSemester?->name ?? 'N/A',
                $this->egc_defer_from_block_number ? ' (Block '.$this->egc_defer_from_block_number.')' : ''
            ),
            StudentActionType::ACADEMIC_RESUME => sprintf(
                'Resume in %s',
                $this->returnSemester?->name ?? 'N/A'
            ),
            StudentActionType::WAITING_COURSE_OPENING => sprintf(
                'Waiting for course opening from %s (Block %s)',
                $this->fromSemester?->name ?? 'N/A',
                $this->egc_defer_from_block_number ?? '?'
            ),
            StudentActionType::ADMISSION_DEFERRAL => sprintf(
                'Admission deferred for %s',
                $this->intendedIntakeSemester?->name ?? 'N/A'
            ),
            StudentActionType::ACADEMIC_DROPOUT => sprintf(
                'Dropout in %s',
                $this->dropoutSemester?->name ?? 'N/A'
            ),
            StudentActionType::CAMPUS_TRANSFER => sprintf(
                'Transfer from %s to %s',
                $this->fromCampus?->name ?? 'N/A',
                $this->toCampus?->name ?? 'N/A'
            ),
        };
    }
}
