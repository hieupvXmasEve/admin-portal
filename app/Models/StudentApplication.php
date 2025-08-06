<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentApplication extends Model
{
    use HasFactory;
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
        'parent_phone',
        'parent_email',
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
        'submitted_photo',
        'submitted_cccd',
        'submitted_ccta',
        'submitted_tn_translate',
        'submitted_hb_translate',
        'submitted_other',
        'submitted_insurance_card',
        'submitted_exemption_gc',
        'study_link_status',
        'english_qualifications',
        'sut_id',
        'is_international_applicant',
        'exception_units',
        'status',
        'student_id',
    ];

    protected $casts = [
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
    ];

    /**
     * Get the student that was created from this application
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Check if this application has been converted to a student
     */
    public function isConverted(): bool
    {
        return !is_null($this->student_id);
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
}
