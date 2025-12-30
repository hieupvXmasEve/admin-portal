<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusTemplate extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\SyllabusTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'title',
        'version',
        'description',
        'total_hours',
        'total_sessions',
        'min_attendance_threshold',
        'min_grade_threshold',
        'learning_outcomes',
        'grading_criteria',
        'required_materials',
        'assessment_policy',
        'applicable_program_id',
        'applicable_campus_id',
        'delivery_mode',
        'is_default',
        'is_active',
        'source_template_id',
        'created_by',
    ];

    protected $casts = [
        'total_hours' => 'integer',
        'total_sessions' => 'integer',
        'min_attendance_threshold' => 'decimal:2',
        'min_grade_threshold' => 'decimal:2',
        'learning_outcomes' => 'array',
        'grading_criteria' => 'array',
        'required_materials' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function applicableProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'applicable_program_id');
    }

    public function applicableCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'applicable_campus_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_template_id');
    }

    public function clonedTemplates(): HasMany
    {
        return $this->hasMany(self::class, 'source_template_id');
    }

    /**
     * Get all assessment components for this syllabus.
     */
    public function assessmentComponents(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class);
    }

    /**
     * Get all course offerings using this syllabus template.
     */
    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getComprehensiveLogFields(): array
    {
        return [
            'unit_id',
            'title',
            'version',
            'description',
            'total_hours',
            'total_sessions',
            'min_attendance_threshold',
            'min_grade_threshold',
            'learning_outcomes',
            'grading_criteria',
            'required_materials',
            'assessment_policy',
            'applicable_program_id',
            'applicable_campus_id',
            'delivery_mode',
            'is_default',
            'is_active',
            'source_template_id',
        ];
    }

    protected function getIdentifierForLog(): string
    {
        $unitCode = $this->unit?->code ?? 'N/A';
        $title = $this->title ?? 'Untitled';
        $version = $this->version ? " (v{$this->version})" : '';

        return "{$unitCode} - {$title}{$version}";
    }
}
