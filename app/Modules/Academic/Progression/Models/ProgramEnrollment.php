<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Models;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEnrollment extends Model
{
    public const LEGACY_STUDENT_SOURCE = 'legacy_students';

    protected $fillable = [
        'student_id',
        'program_id',
        'curriculum_version_id',
        'intake_semester_id',
        'intake_major_semester_id',
        'enrollment_status',
        'study_stage',
        'egc_starting_level',
        'egc_current_level',
        'egc_total_levels',
        'is_primary',
        'source_type',
        'source_id',
        'source_snapshot',
        'materialized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'egc_starting_level' => 'integer',
            'egc_current_level' => 'integer',
            'egc_total_levels' => 'integer',
            'source_snapshot' => 'array',
            'materialized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    public function intakeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_semester_id');
    }

    public function intakeMajorSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'intake_major_semester_id');
    }

    public function isPrimaryActive(): bool
    {
        return $this->is_primary && $this->enrollment_status === 'active';
    }
}
