<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Upload\Models\UploadRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IeltsCertificate extends Model
{
    use HasFactory;

    public const SCORE_THRESHOLD_INTAKE_COURSE = 5.5;

    protected $appends = [
        'formatted_submitted_at',
    ];

    protected $fillable = [
        'student_id',
        'overall_score',
        'submitted_at',
        'upload_record_id',
        'missing_documents',
        'issue_date',
        'notes',
    ];

    protected $casts = [
        'overall_score' => 'decimal:1',
        'submitted_at' => 'datetime',
        'missing_documents' => 'boolean',
        'issue_date' => 'date',
    ];

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function uploadRecord(): BelongsTo
    {
        return $this->belongsTo(UploadRecord::class);
    }

    public function progressionEvents(): HasMany
    {
        return $this->hasMany(AcademicProgressionEvent::class);
    }

    // =====================
    // Business Logic
    // =====================

    public function meetsIntakeCourseRequirement(): bool
    {
        return $this->overall_score >= self::SCORE_THRESHOLD_INTAKE_COURSE;
    }

    public function hasFileScan(): bool
    {
        return $this->upload_record_id !== null && ! $this->missing_documents;
    }

    // =====================
    // Scopes
    // =====================

    public function scopeMeetsThreshold($query, float $threshold = self::SCORE_THRESHOLD_INTAKE_COURSE)
    {
        return $query->where('overall_score', '>=', $threshold);
    }

    public function scopeMissingDocuments($query, bool $missing = true)
    {
        return $query->where('missing_documents', $missing);
    }

    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('submitted_at', 'desc');
    }

    // =====================
    // Accessors
    // =====================

    public function getFormattedSubmittedAtAttribute(): string
    {
        return $this->submitted_at->format('d/m/Y H:i');
    }

    public function getFormattedIssueDateAttribute(): ?string
    {
        return $this->issue_date?->format('d/m/Y');
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->missing_documents) {
            return 'Missing Documents';
        }

        return $this->meetsIntakeCourseRequirement() ? 'Qualified' : 'Below Threshold';
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->missing_documents) {
            return 'orange';
        }

        return $this->meetsIntakeCourseRequirement() ? 'green' : 'gray';
    }
}
