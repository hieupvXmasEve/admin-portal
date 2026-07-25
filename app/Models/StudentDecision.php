<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'decision_name',
        'decision_number',
        'decision_signer',
        'issued_at',
        'expires_at',
        'upload_record_id',
        'changed_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function uploadRecord(): BelongsTo
    {
        return $this->belongsTo(UploadRecord::class, 'upload_record_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(StudentActionLog::class, 'decision_id');
    }

    public function progressionEvents(): HasMany
    {
        return $this->hasMany(AcademicProgressionEvent::class, 'decision_id');
    }

    /**
     * The students this decision covers (its roster, ADR-0048).
     *
     * One decision covers many students; a purely informational decision is a
     * roster with no authorized event. This is the source of truth for coverage,
     * distinct from the per-event authorizing-decision reference.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_decision_student',
            'student_decision_id',
            'student_id'
        )->withTimestamps();
    }

    /**
     * Ensure the given students appear on this decision's coverage roster.
     *
     * Idempotent (syncWithoutDetaching). Called whenever this decision authorizes
     * a transition for a student so the roster stays consistent with the events
     * it authorizes (ADR-0048).
     */
    public function cover(int ...$studentIds): void
    {
        $ids = array_values(array_unique(array_filter($studentIds)));

        if ($ids === []) {
            return;
        }

        $this->students()->syncWithoutDetaching($ids);
    }
}
