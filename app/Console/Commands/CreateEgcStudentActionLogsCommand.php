<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StudentActionType;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateEgcStudentActionLogsCommand extends Command
{
    protected $signature = 'students:create-egc-action-logs
        {from_semester_id : Semester ID to save into from_semester_id}
        {changed_by_user_id : User ID to save into changed_by_user_id}
        {file=data/list_student_egc.txt : Path to file containing student IDs}
        {--mode=pending_to_egc : Processing mode: pending_to_egc, egc_to_course, or pending_to_course}
        {--decision_id= : Optional decision_id to link with student_action_logs}
        {--dry : Preview only, do not write into DB}';

    protected $description = 'Create student action logs in one isolated mode per run: pending_to_egc, egc_to_course, or pending_to_course';

    public function handle(): int
    {
        $fromSemesterId = (int) $this->argument('from_semester_id');
        $changedByUserId = (int) $this->argument('changed_by_user_id');
        $filePath = $this->resolveFilePath((string) $this->argument('file'));
        $mode = strtolower(trim((string) $this->option('mode')));
        $isDryRun = (bool) $this->option('dry');
        $decisionIdOption = $this->option('decision_id');
        $decisionId = ($decisionIdOption === null || $decisionIdOption === '')
            ? null
            : (int) $decisionIdOption;

        if (! is_file($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        if (! Semester::query()->whereKey($fromSemesterId)->exists()) {
            $this->error("Semester not found: {$fromSemesterId}");

            return self::FAILURE;
        }

        if (! User::query()->whereKey($changedByUserId)->exists()) {
            $this->error("User not found: {$changedByUserId}");

            return self::FAILURE;
        }

        if (! in_array($mode, ['pending_to_egc', 'egc_to_course', 'pending_to_course'], true)) {
            $this->error('Invalid mode. Allowed: pending_to_egc, egc_to_course, pending_to_course');

            return self::FAILURE;
        }

        if ($decisionId !== null && ! StudentDecision::query()->whereKey($decisionId)->exists()) {
            $this->error("Decision not found: {$decisionId}");

            return self::FAILURE;
        }

        $studentIds = $this->parseStudentIds($filePath);
        if ($studentIds->isEmpty()) {
            $this->error("No student IDs found in file: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Loaded {$studentIds->count()} student IDs from {$filePath}");
        $this->info(
            "from_semester_id={$fromSemesterId}, changed_by_user_id={$changedByUserId}, mode={$mode}, decision_id="
            .($decisionId ?? 'null')
            .', dry='
            .($isDryRun ? 'yes' : 'no')
        );
        $this->newLine();

        try {
            $students = Student::query()
                ->whereIn('student_id', $studentIds->all())
                ->select(['id', 'student_id', 'full_name', 'status'])
                ->get();
        } catch (QueryException $exception) {
            $this->error('Database error while loading students: '.$exception->getMessage());

            return self::FAILURE;
        }

        $studentMap = $students->keyBy('student_id');
        $missingIds = $studentIds
            ->filter(static fn (string $studentId): bool => ! $studentMap->has($studentId))
            ->values();

        $eligibleStudents = $students->values();
        $lifecycleStatuses = $eligibleStudents->isEmpty()
            ? []
            : app(StudentLifecycleStatusReader::class)
                ->statusesFor($eligibleStudents->pluck('id')->map(static fn (int|string $id): int => (int) $id)->all());

        $plannedOrCreated = collect();
        $skipErrors = collect();

        foreach ($eligibleStudents as $student) {
            try {
                $rowsToCreate = collect();

                if ($mode === 'pending_to_egc') {
                    $exists = StudentActionLog::query()
                        ->where('student_id', $student->id)
                        ->where('previous_status', 'pending')
                        ->where('new_status', 'intake_pre_uni_gc')
                        ->where('from_semester_id', $fromSemesterId)
                        ->exists();

                    if ($exists) {
                        $skipErrors->push([
                            'student_id' => (string) $student->student_id,
                            'error' => 'Skipped: existing log found for pending -> intake_pre_uni_gc with same from_semester_id.',
                        ]);
                    } else {
                        $rowsToCreate->push([
                            'action_type' => StudentActionType::STUDENT_ENROLLMENT_NE->value,
                            'reason' => 'Student completes NE enrollment.',
                            'notes' => "Source file: {$filePath}",
                            'changed_by_user_id' => $changedByUserId,
                            'decision_id' => $decisionId,
                            'from_semester_id' => $fromSemesterId,
                            'previous_status' => 'pending',
                            'new_status' => 'intake_pre_uni_gc',
                            'missing_documents' => false,
                        ]);
                    }
                } elseif ($mode === 'pending_to_course') {
                    $exists = StudentActionLog::query()
                        ->where('student_id', $student->id)
                        ->where('previous_status', 'pending')
                        ->where('new_status', 'intake_course')
                        ->where('from_semester_id', $fromSemesterId)
                        ->exists();

                    if ($exists) {
                        $skipErrors->push([
                            'student_id' => (string) $student->student_id,
                            'error' => 'Skipped: existing log found for pending -> intake_course with same from_semester_id.',
                        ]);
                    } else {
                        $rowsToCreate->push([
                            'action_type' => StudentActionType::STUDENT_ENROLLMENT_NE->value,
                            'reason' => 'Student completes enrollment and directly enters course stage.',
                            'notes' => "Source file: {$filePath}",
                            'changed_by_user_id' => $changedByUserId,
                            'decision_id' => $decisionId,
                            'from_semester_id' => $fromSemesterId,
                            'previous_status' => 'pending',
                            'new_status' => 'intake_course',
                            'missing_documents' => false,
                        ]);
                    }
                } else {
                    $currentStatus = (string) ($lifecycleStatuses[(int) $student->id] ?? $student->status ?? '');
                    if ($currentStatus !== 'intake_course') {
                        $skipErrors->push([
                            'student_id' => (string) $student->student_id,
                            'error' => "Skipped: current status is {$currentStatus}, expected intake_course for mode egc_to_course.",
                        ]);
                    } else {
                        $existingAction = StudentActionLog::query()
                            ->where('student_id', $student->id)
                            ->where('previous_status', 'intake_pre_uni_gc')
                            ->orderByDesc('id')
                            ->first(['id', 'action_type', 'new_status']);

                        if ($existingAction !== null) {
                            $existingActionType = $existingAction->action_type instanceof StudentActionType
                                ? $existingAction->action_type->value
                                : (string) $existingAction->action_type;
                            $skipErrors->push([
                                'student_id' => (string) $student->student_id,
                                'error' => sprintf(
                                    'Skipped: existing action id=%d, action_type=%s, new_status=%s with previous_status=intake_pre_uni_gc.',
                                    (int) $existingAction->id,
                                    $existingActionType,
                                    (string) ($existingAction->new_status ?? 'null')
                                ),
                            ]);
                        } else {
                            $rowsToCreate->push([
                                'action_type' => StudentActionType::STUDENT_MAJOR_ENROLLMENT->value,
                                'reason' => 'Student officially enters major study.',
                                'notes' => "Source file: {$filePath}",
                                'changed_by_user_id' => $changedByUserId,
                                'decision_id' => $decisionId,
                                'from_semester_id' => $fromSemesterId,
                                'previous_status' => 'intake_pre_uni_gc',
                                'new_status' => 'intake_course',
                                'missing_documents' => false,
                            ]);
                        }
                    }
                }

                if ($rowsToCreate->isEmpty()) {
                    continue;
                }

                if (! $isDryRun) {
                    DB::transaction(function () use ($rowsToCreate, $student): void {
                        foreach ($rowsToCreate as $row) {
                            StudentActionLog::query()->create([
                                'student_id' => $student->id,
                                ...$row,
                            ]);
                        }
                    });
                }

                foreach ($rowsToCreate as $row) {
                    $plannedOrCreated->push([
                        'student_id' => (string) $student->student_id,
                        'name' => (string) ($student->full_name ?? '-'),
                        'old_status' => (string) $row['previous_status'],
                        'new_status' => (string) $row['new_status'],
                        'mode' => $isDryRun ? 'DRY-RUN' : 'CREATED',
                    ]);
                }
            } catch (QueryException $exception) {
                $this->error("Failed creating action log for {$student->student_id}: ".$exception->getMessage());
            }
        }

        $this->info('Summary');
        $this->line("- Total IDs in file: {$studentIds->count()}");
        $this->line("- Missing students: {$missingIds->count()}");
        $this->line('- '.($isDryRun ? 'Planned actions' : 'Created actions').": {$plannedOrCreated->count()}");
        $this->line("- Skip errors: {$skipErrors->count()}");
        $this->newLine();

        if ($missingIds->isNotEmpty()) {
            $this->warn('Missing students:');
            $this->table(
                ['Student ID', 'Reason'],
                $missingIds->map(static fn (string $studentId): array => [
                    $studentId,
                    'Student not found in students table by student_id.',
                ])->toArray()
            );
        }

        if ($plannedOrCreated->isNotEmpty()) {
            $this->info($isDryRun ? 'Planned actions:' : 'Created actions:');
            $this->table(
                ['Student ID', 'Name', 'Previous Status', 'New Status', 'Mode'],
                $plannedOrCreated->map(static fn (array $row): array => [
                    $row['student_id'],
                    $row['name'],
                    $row['old_status'],
                    $row['new_status'],
                    $row['mode'],
                ])->toArray()
            );
        }

        if ($skipErrors->isNotEmpty()) {
            $this->warn('Skip errors:');
            $this->table(
                ['Student ID', 'Error'],
                $skipErrors->map(static fn (array $row): array => [
                    $row['student_id'],
                    $row['error'],
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    private function parseStudentIds(string $filePath): Collection
    {
        $content = trim((string) file_get_contents($filePath));

        if ($content === '') {
            return collect();
        }

        return collect(preg_split('/[\s,;]+/', $content) ?: [])
            ->map(static fn (string $value): string => trim($value))
            ->filter(static fn (string $value): bool => $value !== '' && strtoupper($value) !== 'MSSV')
            ->unique()
            ->values();
    }

    private function resolveFilePath(string $inputPath): string
    {
        if (is_file($inputPath)) {
            return $inputPath;
        }

        $projectPath = base_path($inputPath);

        return is_file($projectPath) ? $projectPath : $inputPath;
    }
}
