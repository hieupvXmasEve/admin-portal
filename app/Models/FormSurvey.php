<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'form_version_id',
        'course_offering_id',
    ];

    /**
     * Get the form that owns the survey.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the form version used for the survey.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the course offering that this survey is attached to.
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * Get the student survey tasks for this form survey.
     */
    public function studentSurveys(): HasMany
    {
        return $this->hasMany(StudentFormSurvey::class);
    }

    /**
     * Get pending student surveys.
     */
    public function pendingStudentSurveys(): HasMany
    {
        return $this->hasMany(StudentFormSurvey::class)
            ->where('status', '!=', 'completed');
    }
}
