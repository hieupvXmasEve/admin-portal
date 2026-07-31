<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\Student;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Console\Command;

class BackfillProgramEnrollmentsCommand extends Command
{
    protected $signature = 'academic:backfill-program-enrollments
        {--student-id= : Backfill one Student primary key}
        {--check : Fail when a Student has no primary Program Enrollment}';

    protected $description = 'Materialize legacy Student program and lifecycle facts as Program Enrollments.';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->checkPrimaryEnrollments();
        }

        $created = 0;
        $updated = 0;
        $studentId = $this->option('student-id');

        Student::query()
            ->when($studentId !== null, fn ($query) => $query->whereKey((int) $studentId))
            ->orderBy('id')
            ->chunkById(100, function ($students) use (&$created, &$updated): void {
                foreach ($students as $student) {
                    $enrollment = MaterializeProgramEnrollmentAction::run([
                        'student_id' => $student->id,
                    ]);

                    if ($enrollment->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            });

        $this->info("Program enrollment materialization complete: {$created} created, {$updated} refreshed.");

        return self::SUCCESS;
    }

    private function checkPrimaryEnrollments(): int
    {
        $missing = Student::query()
            ->whereNotExists(function ($enrollments): void {
                $enrollments->selectRaw('1')
                    ->from((new ProgramEnrollment)->getTable())
                    ->whereColumn('program_enrollments.student_id', 'students.id')
                    ->where('program_enrollments.is_primary', true);
            })
            ->count();

        if ($missing === 0) {
            $this->info('Program enrollment preflight passed: every Student has a primary Program Enrollment.');

            return self::SUCCESS;
        }

        $this->error("Program enrollment preflight failed: {$missing} student(s) have no primary Program Enrollment.");

        return self::FAILURE;
    }
}
