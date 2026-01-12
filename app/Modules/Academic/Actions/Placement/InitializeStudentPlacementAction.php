<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InitializeStudentPlacementAction
{
    /**
     * Initialize a student's academic placement.
     *
     * Cases:
     * A) IELTS >= 6.5 with scan -> intake_course
     * B) IELTS >= 6.5 without scan (exception) -> intake_course + missing_documents
     * C) IELTS < 6.5 -> intake_pre_uni_gc + level based on score
     * D) No IELTS (placement test) -> intake_pre_uni_gc + assigned level
     *
     * @param array $data {
     *     student_id: int,
     *     semester_id: int,
     *     has_ielts: bool,
     *     ielts_score: ?float (required if has_ielts),
     *     upload_record_id: ?int (file scan),
     *     missing_documents: ?bool,
     *     english_level: ?int (0-5, required if no IELTS or IELTS < 6.5),
     *     trigger_source: ?string,
     *     notes: ?string,
     * }
     */
    public static function run(array $data): Student
    {
        $studentId = $data['student_id'];
        $semesterId = $data['semester_id'];
        $hasIelts = $data['has_ielts'] ?? false;
        $userId = $data['created_by_user_id'] ?? Auth::id();

        $student = Student::findOrFail($studentId);

        // Validate: placement should only happen once (or when status is pending)
        self::validateCanInitializePlacement($student);

        return DB::transaction(function () use ($student, $data, $semesterId, $hasIelts, $userId) {
            $ieltsCertificate = null;
            $courseStage = null;
            $englishLevel = null;
            $triggerSource = ProgressionTriggerSource::from($data['trigger_source'] ?? 'manual_admin');

            if ($hasIelts) {
                $ieltsCertificate = self::recordIeltsCertificate($student, $data);
                $ieltsScore = (float) $data['ielts_score'];

                if ($ieltsScore >= IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE) {
                    // Case A or B: IELTS >= 6.5
                    $courseStage = 'intake_course';
                    $triggerSource = ProgressionTriggerSource::IELTS;
                } else {
                    // Case C: IELTS < 6.5
                    $courseStage = 'intake_pre_uni_gc';
                    $englishLevel = $data['english_level'] ?? self::mapIeltsToLevel($ieltsScore);
                    $triggerSource = ProgressionTriggerSource::IELTS;
                }
            } else {
                // Case D: No IELTS - use placement test
                $courseStage = 'intake_pre_uni_gc';
                $englishLevel = $data['english_level'] ?? 0;
                $triggerSource = ProgressionTriggerSource::PLACEMENT_TEST;
            }

            // Update student snapshot
            $student->update([
                'status' => $courseStage,
                'gc_starting_level' => $englishLevel,
                'gc_current_level' => $englishLevel,
                'status_change_date' => now()->toDateString(),
                'status_changed_by' => $userId,
            ]);

            // Create IELTS_RECORDED event if applicable
            if ($ieltsCertificate) {
                AcademicProgressionEvent::create([
                    'student_id' => $student->id,
                    'event_type' => AcademicProgressionEventType::IELTS_RECORDED,
                    'semester_id' => $semesterId,
                    'effective_at' => now(),
                    'trigger_source' => ProgressionTriggerSource::IELTS,
                    'created_by_user_id' => $userId,
                    'ielts_certificate_id' => $ieltsCertificate->id,
                    'notes' => sprintf('IELTS score %s recorded at placement', $ieltsCertificate->overall_score),
                ]);
            }

            // Create PLACEMENT_INITIALIZED event
            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::PLACEMENT_INITIALIZED,
                'semester_id' => $semesterId,
                'effective_at' => now(),
                'trigger_source' => $triggerSource,
                'created_by_user_id' => $userId,
                'to_course_stage' => $courseStage,
                'to_english_level' => $englishLevel,
                'ielts_certificate_id' => $ieltsCertificate?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            Log::info('Student placement initialized', [
                'student_id' => $student->id,
                'course_stage' => $courseStage,
                'english_level' => $englishLevel,
                'has_ielts' => $hasIelts,
                'ielts_score' => $ieltsCertificate?->overall_score,
                'created_by' => $userId,
            ]);

            return $student->fresh();
        });
    }

    /**
     * Validate student can have placement initialized.
     */
    private static function validateCanInitializePlacement(Student $student): void
    {
        // Only allow initialization for pending students or those without placement events
        if ($student->status !== 'pending') {
            $hasPlacement = $student->academicProgressionEvents()
                ->byEventType(AcademicProgressionEventType::PLACEMENT_INITIALIZED)
                ->exists();

            if ($hasPlacement) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student has already been placed. Use update actions instead.',
                ]);
            }
        }
    }

    /**
     * Record IELTS certificate for the student.
     */
    private static function recordIeltsCertificate(Student $student, array $data): IeltsCertificate
    {
        $missingDocuments = empty($data['upload_record_id']) || ($data['missing_documents'] ?? false);

        return IeltsCertificate::create([
            'student_id' => $student->id,
            'overall_score' => $data['ielts_score'],
            'submitted_at' => now(),
            'upload_record_id' => $data['upload_record_id'] ?? null,
            'missing_documents' => $missingDocuments,
            'issue_date' => $data['issue_date'] ?? null,
            'notes' => $data['ielts_notes'] ?? null,
        ]);
    }

    /**
     * Map IELTS score to English level (0-5).
     * This is a simplified mapping - adjust based on actual business rules.
     */
    private static function mapIeltsToLevel(float $score): int
    {
        return match (true) {
            $score >= 6.0 => 5,
            $score >= 5.5 => 4,
            $score >= 5.0 => 3,
            $score >= 4.5 => 2,
            $score >= 4.0 => 1,
            default => 0,
        };
    }
}
