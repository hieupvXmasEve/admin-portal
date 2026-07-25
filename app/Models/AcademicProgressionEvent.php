<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicProgressionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'event_type',
        'semester_id',
        'effective_at',
        'trigger_source',
        'created_by_user_id',
        'from_course_stage',
        'to_course_stage',
        'from_english_level',
        'to_english_level',
        'ielts_certificate_id',
        'decision_id',
        'notes',
    ];

    protected $casts = [
        'event_type' => AcademicProgressionEventType::class,
        'trigger_source' => ProgressionTriggerSource::class,
        'effective_at' => 'datetime',
        'from_english_level' => 'integer',
        'to_english_level' => 'integer',
    ];

    protected $with = ['createdBy'];

    protected $appends = ['summary', 'event_type_label_en', 'trigger_source_label'];

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function ieltsCertificate(): BelongsTo
    {
        return $this->belongsTo(IeltsCertificate::class);
    }

    /**
     * The decision that authorized this transition, if any (ADR-0048).
     *
     * Nullable: a requires-decision transition can be recorded first and the
     * decision attached later. Records authorization provenance.
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(StudentDecision::class, 'decision_id');
    }

    // =====================
    // Scopes
    // =====================

    public function scopeByEventType($query, string|AcademicProgressionEventType $eventType)
    {
        $type = $eventType instanceof AcademicProgressionEventType ? $eventType->value : $eventType;

        return $query->where('event_type', $type);
    }

    public function scopeByTriggerSource($query, string|ProgressionTriggerSource $source)
    {
        $sourceValue = $source instanceof ProgressionTriggerSource ? $source->value : $source;

        return $query->where('trigger_source', $sourceValue);
    }

    public function scopeBySemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('effective_at', '>=', $from);
        }
        if ($to) {
            $query->where('effective_at', '<=', $to);
        }

        return $query;
    }

    public function scopeStageChanges($query)
    {
        return $query->byEventType(AcademicProgressionEventType::COURSE_STAGE_CHANGED);
    }

    public function scopeLevelChanges($query)
    {
        return $query->byEventType(AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED);
    }

    public function scopePlacementEvents($query)
    {
        return $query->byEventType(AcademicProgressionEventType::PLACEMENT_INITIALIZED);
    }

    public function scopeIeltsEvents($query)
    {
        return $query->byEventType(AcademicProgressionEventType::IELTS_RECORDED);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('effective_at', 'desc');
    }

    // =====================
    // Accessors
    // =====================

    public function getEventTypeLabelAttribute(): string
    {
        return $this->event_type->label();
    }

    public function getEventTypeLabelEnAttribute(): string
    {
        return $this->event_type->labelEn();
    }

    public function getTriggerSourceLabelAttribute(): string
    {
        return $this->trigger_source->label();
    }

    public function getFormattedEffectiveAtAttribute(): string
    {
        return $this->effective_at->format('d/m/Y H:i');
    }

    public function getSummaryAttribute(): string
    {
        return match ($this->event_type) {
            AcademicProgressionEventType::PLACEMENT_INITIALIZED => $this->buildPlacementSummary(),
            AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED => $this->buildLevelChangeSummary(),
            AcademicProgressionEventType::COURSE_STAGE_CHANGED => $this->buildStageChangeSummary(),
            AcademicProgressionEventType::IELTS_RECORDED => $this->buildIeltsSummary(),
        };
    }

    // =====================
    // Helper Methods
    // =====================

    private function buildPlacementSummary(): string
    {
        $stage = $this->to_course_stage ?? 'N/A';
        $level = $this->to_english_level !== null ? "Level {$this->to_english_level}" : '';

        return sprintf('Placed into %s %s', $stage, $level);
    }

    private function buildLevelChangeSummary(): string
    {
        return sprintf(
            'Level changed from %d to %d',
            $this->from_english_level ?? 0,
            $this->to_english_level ?? 0
        );
    }

    private function buildStageChangeSummary(): string
    {
        return sprintf(
            'Stage changed from %s to %s',
            $this->from_course_stage ?? 'N/A',
            $this->to_course_stage ?? 'N/A'
        );
    }

    private function buildIeltsSummary(): string
    {
        $score = $this->ieltsCertificate?->overall_score ?? 'N/A';

        return sprintf('IELTS score %s recorded', $score);
    }
}
