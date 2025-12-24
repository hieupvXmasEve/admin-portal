<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Form;
use App\Actions\Form\CreateFormTargetAction;
use App\Actions\Form\GenerateStudentAssignmentsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CourseSurveyService
{
    public function __construct(
        protected SystemConfigService $systemConfigService,
        protected CreateFormTargetAction $createFormTargetAction,
        protected GenerateStudentAssignmentsAction $generateStudentAssignmentsAction
    ) {}

    /**
     * Automatically attach survey to course offering when it's completed.
     * This method is idempotent - won't create duplicates.
     */
    public function attachSurveyToCompletedCourse(CourseOffering $courseOffering): bool
    {
        // Check if survey feature is enabled
        $surveyEnabled = $this->systemConfigService->get('survey_enabled', false);
        if (! $surveyEnabled) {
            Log::info('Survey feature is disabled, skipping survey attachment', [
                'course_offering_id' => $courseOffering->id,
            ]);
            return false;
        }

        // Exclude EGC units
        $courseOffering->load('unit');
        if ($courseOffering->unit && $courseOffering->unit->unit_type === 'egc') {
            Log::info('Skipping survey attachment for EGC unit', [
                'course_offering_id' => $courseOffering->id,
                'unit_type' => $courseOffering->unit->unit_type,
            ]);
            return false;
        }

        // Check if survey already exists for this course
        if ($courseOffering->formTargets()->exists()) {
            Log::info('Survey already exists for course offering, skipping', [
                'course_offering_id' => $courseOffering->id,
            ]);
            return true;
        }

        // Get default form survey
        $defaultFormId = $this->systemConfigService->get('default_course_survey');
        if (! $defaultFormId) {
            Log::warning('No default course survey configured', [
                'course_offering_id' => $courseOffering->id,
            ]);
            return false;
        }

        try {
            return DB::transaction(function () use ($courseOffering, $defaultFormId) {
                $semester = $courseOffering->semester;
                $startAt = now();
                // Default end_at to 2 weeks after semester end, or 4 weeks from now if no semester end
                $endAt = $semester ? Carbon::parse($semester->end_date)->addWeeks(2) : now()->addWeeks(4);

                $formTarget = $this->createFormTargetAction->execute([
                    'form_id' => $defaultFormId,
                    'campus_id' => $courseOffering->campus_id,
                    'scope_type' => 'course',
                    'scope_id' => $courseOffering->id,
                    'semester_id' => $courseOffering->semester_id,
                    'start_at' => $startAt,
                    'end_at' => null,
                    'status' => 'active',
                    'is_mandatory' => true,
                    'submission_limit_per_user' => 1,
                ]);

                // Generate student assignments
                $this->generateStudentAssignmentsAction->execute($formTarget);

                Log::info('Form target and assignments created via Service for course offering', [
                    'form_target_id' => $formTarget->id,
                    'course_offering_id' => $courseOffering->id,
                    'form_id' => $defaultFormId,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to attach survey at completion: ' . $e->getMessage(), [
                'course_offering_id' => $courseOffering->id,
                'exception' => $e
            ]);
            return false;
        }
    }
}
