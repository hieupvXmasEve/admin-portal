<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use App\Models\StudentApplication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reported score for a subject on an Application, from either the school
 * report or the national exam. Absence of a row means "not reported", distinct
 * from a reported score of 0.
 */
class ApplicationAcademicScore extends Model
{
    use HasFactory;

    public const SOURCE_SCHOOL_REPORT = 'school_report';

    public const SOURCE_NATIONAL_EXAM = 'national_exam';

    protected $fillable = [
        'student_application_id',
        'subject_code',
        'score',
        'source',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'student_application_id');
    }
}
