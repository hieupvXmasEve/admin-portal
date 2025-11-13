<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFormSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_survey_id',
        'student_id',
        'status',
        'response_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Get the form survey that owns this student survey task.
     */
    public function formSurvey(): BelongsTo
    {
        return $this->belongsTo(FormSurvey::class);
    }

    /**
     * Get the student who needs to complete this survey.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the response when survey is completed.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    /**
     * Mark the survey as completed.
     */
    public function markAsCompleted(?int $responseId = null): void
    {
        $this->update([
            'status' => 'completed',
            'response_id' => $responseId,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if the survey is pending (not completed).
     */
    public function isPending(): bool
    {
        return $this->status !== 'completed';
    }
}
