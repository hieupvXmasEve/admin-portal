<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\Student;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use Illuminate\Console\Command;

class BackfillProgramEnrollmentsCommand extends Command
{
    protected $signature = 'academic:backfill-program-enrollments {--student-id= : Backfill one Student primary key}';

    protected $description = 'Materialize legacy Student program and lifecycle facts as Program Enrollments.';

    public function handle(): int
    {
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
}
