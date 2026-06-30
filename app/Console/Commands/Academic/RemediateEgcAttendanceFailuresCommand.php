<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Actions\RemediateEgcAttendanceFailuresAction;
use Illuminate\Console\Command;

class RemediateEgcAttendanceFailuresCommand extends Command
{
    protected $signature = 'academic:remediate-egc-attendance-failures
                            {--dry-run : Preview changes without writing to the database}
                            {--student-id= : Limit to one student (student_id code or PK)}
                            {--course-offering-id= : Limit to one EGC course offering}';

    protected $description = 'Flip minimum absent EGC attendances to present for grade-pass / attendance-fail records in the active semester';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $studentId = $this->option('student-id');
        $courseOfferingId = $this->option('course-offering-id');

        if ($dryRun) {
            $this->warn('Dry run — no database writes will be performed.');
        }

        $stats = RemediateEgcAttendanceFailuresAction::run(
            dryRun: $dryRun,
            studentIdentifier: is_string($studentId) && $studentId !== '' ? $studentId : null,
            courseOfferingId: $courseOfferingId !== null ? (int) $courseOfferingId : null,
        );

        if ($stats['details'] !== []) {
            $this->table(
                ['Student', 'Unit', 'Status', 'Flipped', 'Before %', 'After %', 'Message'],
                collect($stats['details'])->map(fn (array $row): array => [
                    $row['student_code'] ?? 'N/A',
                    $row['unit_code'] ?? 'N/A',
                    $row['status'],
                    $row['attendances_flipped'],
                    $row['attendance_before'] ?? '—',
                    $row['attendance_after'] ?? '—',
                    $row['message'],
                ])->all(),
            );
        } else {
            $this->info('No matching EGC records found in the active semester.');
        }

        $this->newLine();
        $this->info("Candidates: {$stats['candidates']}");
        $this->info("Remediated: {$stats['remediated']}");
        $this->info("Skipped: {$stats['skipped']}");
        $this->info("Attendance rows flipped: {$stats['attendances_flipped']}");

        if (! $dryRun) {
            $this->info("EGC block semesters synced: {$stats['semesters_synced']}");
        }

        return self::SUCCESS;
    }
}
