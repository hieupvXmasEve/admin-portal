<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;

/**
 * Shared fixture for S-003 data-guard tests. A valid student needs intake fields
 * that have no DB default, so build the full campus/program/curriculum chain the
 * same way the other finance tests do. Guarded with function_exists so multiple
 * test files can require_once it in one Pest process.
 */
if (! function_exists('makeGuardStudent')) {
    function makeGuardStudent(): Student
    {
        $campus = Campus::factory()->create();
        $program = Program::factory()->create();
        $semester = Semester::factory()->active()->create();
        $curriculumVersion = CurriculumVersion::factory()
            ->forProgram($program)
            ->withEffectiveSemester($semester)
            ->create();

        return Student::factory()
            ->forCampus($campus)
            ->forProgram($program)
            ->state([
                'curriculum_version_id' => $curriculumVersion->id,
                'intake_semester_id' => $semester->id,
                'intake' => 1,
                'intake_mode' => 'sequential',
            ])
            ->create();
    }
}
