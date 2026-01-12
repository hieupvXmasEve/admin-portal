<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\AcademicRecord;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\StudentChange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateStudentProgressionEventsCommand extends Command
{
    protected $signature = 'academic:migrate-progression-events
                            {--dry-run : Simulate the migration without saving}
                            {--student-id= : Process a specific student only}';

    protected $description = 'Migrate existing student data to academic progression events';

    private const SEMESTER_ID = 1;

    private const DEFAULT_IELTS_SCORE = 5.5;

    private int $processed = 0;

    private int $skipped = 0;

    private int $errors = 0;

    public function handle(): int
    {
        $this->info('Starting student progression events migration...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be saved.');
        }

        $query = Student::query()
            ->whereIn('status', ['intake_pre_uni_gc', 'intake_course', 'deferred']);

        if ($studentId = $this->option('student-id')) {
            $query->where('id', $studentId);
        }

        $students = $query->get();

        $this->info("Found {$students->count()} students to process.");

        $progressBar = $this->output->createProgressBar($students->count());
        $progressBar->start();

        foreach ($students as $student) {
            $this->processStudent($student);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Migration completed:");
        $this->info("  - Processed: {$this->processed}");
        $this->info("  - Skipped: {$this->skipped}");
        $this->info("  - Errors: {$this->errors}");

        return Command::SUCCESS;
    }

    private function processStudent(Student $student): void
    {
        // Idempotency check
        if ($this->hasPlacementEvent($student)) {
            $this->skipped++;
            $this->logVerbose("Skipping student {$student->id} - already has placement event");

            return;
        }

        try {
            if ($this->option('dry-run')) {
                $this->simulateProcessing($student);
            } else {
                DB::transaction(function () use ($student) {
                    $this->migrateStudent($student);
                });
            }
            $this->processed++;
        } catch (\Exception $e) {
            $this->errors++;
            $this->error("Error processing student {$student->id}: {$e->getMessage()}");
            Log::error('Migration error', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function hasPlacementEvent(Student $student): bool
    {
        return AcademicProgressionEvent::query()
            ->byStudent($student->id)
            ->placementEvents()
            ->exists();
    }

    private function migrateStudent(Student $student): void
    {
        $effectiveStatus = $student->status;

        // For deferred students, find previous status
        if ($effectiveStatus === 'deferred') {
            $effectiveStatus = $this->getPreviousStatus($student);
            if (! $effectiveStatus) {
                $this->warn("  Student {$student->id}: No previous status found for deferred student, skipping");
                $this->skipped++;

                return;
            }
        }

        // Process based on effective status
        if ($effectiveStatus === 'intake_pre_uni_gc') {
            $this->processPreUniGc($student);
        } elseif ($effectiveStatus === 'intake_course') {
            if ($student->gc_starting_level !== null) {
                $this->processIntakeCourseWithGc($student);
            } else {
                $this->processIntakeCourseDirectIelts($student);
            }
        }
    }

    private function simulateProcessing(Student $student): void
    {
        $effectiveStatus = $student->status;

        if ($effectiveStatus === 'deferred') {
            $effectiveStatus = $this->getPreviousStatus($student);
            if (! $effectiveStatus) {
                $this->logVerbose("  [DRY-RUN] Student {$student->id}: Would skip - no previous status for deferred");

                return;
            }
        }

        $this->logVerbose("  [DRY-RUN] Student {$student->id} (status: {$student->status}, effective: {$effectiveStatus}):");

        if ($effectiveStatus === 'intake_pre_uni_gc') {
            $startLevel = $student->gc_starting_level ?? 0;
            $currentLevel = $student->gc_current_level ?? 0;
            $this->logVerbose("    -> Would create PLACEMENT_INITIALIZED at level {$startLevel}");

            $passedRecords = $this->getPassedEgcRecords($student);
            $simLevel = $startLevel;
            foreach ($passedRecords as $record) {
                if ($record->unit->level === $simLevel) {
                    $newLevel = $simLevel + 1;
                    $this->logVerbose("    -> Would create ENGLISH_LEVEL_CHANGED for unit {$record->unit->code} (level {$simLevel} -> {$newLevel})");
                    $simLevel = $newLevel;
                }
            }
        } elseif ($effectiveStatus === 'intake_course') {
            if ($student->gc_starting_level !== null) {
                $this->logVerbose("    -> Would create PLACEMENT_INITIALIZED at pre_uni_gc level {$student->gc_starting_level}");

                $passedRecords = $this->getPassedEgcRecords($student);
                $simLevel = $student->gc_starting_level;
                foreach ($passedRecords as $record) {
                    if ($record->unit->level === $simLevel) {
                        $newLevel = $simLevel + 1;
                        $this->logVerbose("    -> Would create ENGLISH_LEVEL_CHANGED for unit {$record->unit->code} (level {$simLevel} -> {$newLevel})");
                        $simLevel = $newLevel;
                    }
                }

                $this->logVerbose("    -> Would create IELTS certificate (5.5)");
                $this->logVerbose("    -> Would create COURSE_STAGE_CHANGED");
            } else {
                $score = $this->getIeltsScoreFromApplication($student);
                $this->logVerbose("    -> Would create IELTS certificate (score: {$score})");
                $this->logVerbose("    -> Would create PLACEMENT_INITIALIZED at intake_course");
            }
        }
    }

    /**
     * Case 1: intake_pre_uni_gc students
     */
    private function processPreUniGc(Student $student): void
    {
        $startLevel = $student->gc_starting_level ?? 0;

        // Create PLACEMENT_INITIALIZED
        $this->createPlacementEvent($student, 'intake_pre_uni_gc', $startLevel);

        // Create level change events based on academic records
        $this->createLevelChangeEvents($student, $startLevel);
    }

    /**
     * Case 2: intake_course without gc_starting_level (direct IELTS placement)
     */
    private function processIntakeCourseDirectIelts(Student $student): void
    {
        $score = $this->getIeltsScoreFromApplication($student);
        $needsReview = $score !== $this->getRawIeltsScoreFromApplication($student);

        // Create IELTS certificate
        $certificate = $this->createIeltsCertificate($student, $score, $needsReview);

        // Create IELTS_RECORDED event
        $this->createIeltsRecordedEvent($student, $certificate);

        // Create PLACEMENT_INITIALIZED directly into intake_course
        $this->createPlacementEvent($student, 'intake_course', null, $certificate);
    }

    /**
     * Case 3: intake_course with gc_starting_level (transitioned from pre-uni GC)
     */
    private function processIntakeCourseWithGc(Student $student): void
    {
        $startLevel = $student->gc_starting_level ?? 0;

        // Create PLACEMENT_INITIALIZED at pre_uni_gc
        $this->createPlacementEvent($student, 'intake_pre_uni_gc', $startLevel);

        // Create level change events
        $this->createLevelChangeEvents($student, $startLevel);

        // Create IELTS certificate (default 5.5)
        $certificate = $this->createIeltsCertificate($student, self::DEFAULT_IELTS_SCORE, true);

        // Create IELTS_RECORDED event
        $this->createIeltsRecordedEvent($student, $certificate);

        // Create COURSE_STAGE_CHANGED
        $this->createStageChangeEvent($student, $certificate);
    }

    private function getPreviousStatus(Student $student): ?string
    {
        $change = StudentChange::query()
            ->where('student_id', $student->id)
            ->where('field_name', 'status')
            ->where('new_value', 'deferred')
            ->orderBy('changed_at', 'desc')
            ->first();

        if (! $change) {
            return null;
        }

        $previousStatus = $change->old_value;

        // Only return if it's a status we can process
        if (in_array($previousStatus, ['intake_pre_uni_gc', 'intake_course'])) {
            return $previousStatus;
        }

        return null;
    }

    private function getPassedEgcRecords(Student $student): \Illuminate\Database\Eloquent\Collection
    {
        return AcademicRecord::query()
            ->where('student_id', $student->id)
            ->whereHas('unit', function ($query) {
                $query->where('unit_type', 'egc');
            })
            ->where(function ($query) {
                $query->where('is_passed', true)
                    ->orWhere('override_pass', true);
            })
            ->with(['unit'])
            ->orderBy('created_at')
            ->get();
    }

    private function getIeltsScoreFromApplication(Student $student): float
    {
        $raw = $this->getRawIeltsScoreFromApplication($student);

        if ($raw === null || $raw < self::DEFAULT_IELTS_SCORE) {
            return self::DEFAULT_IELTS_SCORE;
        }

        return $raw;
    }

    private function getRawIeltsScoreFromApplication(Student $student): ?float
    {
        $application = StudentApplication::query()
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $application || $application->overall === null) {
            return null;
        }

        return (float) $application->overall;
    }

    private function createPlacementEvent(
        Student $student,
        string $courseStage,
        ?int $englishLevel,
        ?IeltsCertificate $certificate = null
    ): AcademicProgressionEvent {
        $notes = 'Migration: Created from existing student data';

        return AcademicProgressionEvent::create([
            'student_id' => $student->id,
            'event_type' => AcademicProgressionEventType::PLACEMENT_INITIALIZED,
            'semester_id' => self::SEMESTER_ID,
            'effective_at' => $student->created_at ?? now(),
            'trigger_source' => ProgressionTriggerSource::SYSTEM,
            'to_course_stage' => $courseStage,
            'to_english_level' => $englishLevel,
            'ielts_certificate_id' => $certificate?->id,
            'notes' => $notes,
        ]);
    }

    private function createLevelChangeEvents(Student $student, int $startLevel): void
    {
        $passedRecords = $this->getPassedEgcRecords($student);
        $currentLevel = $startLevel;
        $processedLevels = []; // Track processed levels to avoid duplicates

        foreach ($passedRecords as $record) {
            $unitLevel = $record->unit->level;

            if ($unitLevel === null) {
                continue;
            }

            // When passing a unit at level X, student progresses FROM level X TO level X+1
            // Skip if this unit level doesn't match current level (student retook or wrong order)
            if ($unitLevel !== $currentLevel) {
                continue;
            }

            // Skip if we already processed this level (student retook same level)
            if (in_array($unitLevel, $processedLevels, true)) {
                continue;
            }

            $newLevel = $unitLevel + 1;
            $notes = "Migration: Passed unit {$record->unit->code}";

            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $record->semester_id ?? self::SEMESTER_ID,
                'effective_at' => $record->created_at ?? now(),
                'trigger_source' => ProgressionTriggerSource::SYSTEM,
                'from_english_level' => $currentLevel,
                'to_english_level' => $newLevel,
                'notes' => $notes,
            ]);

            $processedLevels[] = $unitLevel;
            $currentLevel = $newLevel;
        }
    }

    private function createIeltsCertificate(Student $student, float $score, bool $needsReview): IeltsCertificate
    {
        $notes = 'Migration: Created from existing student data';
        if ($needsReview) {
            $notes .= ' - NEEDS REVIEW (score adjusted or missing)';
        }

        return IeltsCertificate::create([
            'student_id' => $student->id,
            'overall_score' => $score,
            'submitted_at' => $student->created_at ?? now(),
            'missing_documents' => true,
            'notes' => $notes,
        ]);
    }

    private function createIeltsRecordedEvent(Student $student, IeltsCertificate $certificate): AcademicProgressionEvent
    {
        return AcademicProgressionEvent::create([
            'student_id' => $student->id,
            'event_type' => AcademicProgressionEventType::IELTS_RECORDED,
            'semester_id' => self::SEMESTER_ID,
            'effective_at' => $certificate->submitted_at,
            'trigger_source' => ProgressionTriggerSource::IELTS,
            'ielts_certificate_id' => $certificate->id,
            'notes' => "Migration: IELTS score {$certificate->overall_score} recorded",
        ]);
    }

    private function createStageChangeEvent(Student $student, IeltsCertificate $certificate): AcademicProgressionEvent
    {
        return AcademicProgressionEvent::create([
            'student_id' => $student->id,
            'event_type' => AcademicProgressionEventType::COURSE_STAGE_CHANGED,
            'semester_id' => self::SEMESTER_ID,
            'effective_at' => now(),
            'trigger_source' => ProgressionTriggerSource::IELTS,
            'from_course_stage' => 'intake_pre_uni_gc',
            'to_course_stage' => 'intake_course',
            'ielts_certificate_id' => $certificate->id,
            'notes' => 'Migration: Transitioned to intake_course based on existing status',
        ]);
    }

    private function logVerbose(string $message): void
    {
        if ($this->output->isVerbose() || $this->option('dry-run')) {
            $this->line($message);
        }
    }
}
