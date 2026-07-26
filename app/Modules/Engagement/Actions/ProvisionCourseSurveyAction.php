<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Models\Form;
use App\Models\FormTarget;
use App\Modules\Engagement\Actions\Forms\CreateFormTargetAction;
use App\Modules\Engagement\Actions\Forms\GenerateStudentAssignmentsAction;
use App\Shared\Contracts\Academic\DTO\CourseOfferingSurveyContext;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ProvisionCourseSurveyAction
{
    public function __construct(
        protected SystemConfigurationReader $systemConfiguration,
        protected CreateFormTargetAction $createFormTargetAction,
        protected GenerateStudentAssignmentsAction $generateStudentAssignmentsAction
    ) {}

    /**
     * Automatically attach survey to course offering when it's completed.
     * This method is idempotent - won't create duplicates.
     */
    public function attachSurveyToCompletedCourse(CourseOfferingSurveyContext $context): bool
    {
        // Check if survey feature is enabled
        $surveyEnabled = $this->systemConfiguration->get('survey_enabled', false);
        if (! $surveyEnabled) {
            Log::info('Survey feature is disabled, skipping survey attachment', [
                'course_offering_id' => $context->courseOfferingId,
            ]);

            return false;
        }

        // Exclude EGC units
        if ($context->unitType === 'egc') {
            Log::info('Skipping survey attachment for EGC unit', [
                'course_offering_id' => $context->courseOfferingId,
                'unit_type' => $context->unitType,
            ]);

            return false;
        }

        // Check if survey already exists for this course
        if ($this->hasSurveyForCourseOffering($context->courseOfferingId)) {
            Log::info('Survey already exists for course offering, skipping', [
                'course_offering_id' => $context->courseOfferingId,
            ]);

            return true;
        }

        // Get default form survey
        $defaultFormId = $this->systemConfiguration->get('default_course_survey');
        if (! $defaultFormId) {
            Log::warning('No default course survey configured', [
                'course_offering_id' => $context->courseOfferingId,
            ]);

            return false;
        }

        try {
            return DB::transaction(function () use ($context, $defaultFormId) {
                $startAt = now();
                // Default end_at to 2 weeks after semester end, or 4 weeks from now if no semester end
                $endAt = $context->semesterEndDate !== null
                    ? Carbon::parse($context->semesterEndDate)->addWeeks(2)
                    : now()->addWeeks(4);

                $formTarget = $this->createFormTargetAction->execute([
                    'form_id' => $defaultFormId,
                    'campus_id' => $context->campusId,
                    'scope_type' => 'course',
                    'scope_id' => $context->courseOfferingId,
                    'semester_id' => $context->semesterId,
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
                    'course_offering_id' => $context->courseOfferingId,
                    'form_id' => $defaultFormId,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to attach survey at completion: '.$e->getMessage(), [
                'course_offering_id' => $context->courseOfferingId,
                'exception' => $e,
            ]);

            return false;
        }
    }

    public function provisionForCourseOffering(CourseOfferingSurveyContext $context, int $formId): FormTarget
    {
        if ($this->hasSurveyForCourseOffering($context->courseOfferingId)) {
            throw ValidationException::withMessages([
                'course_offering' => 'A survey has already been created for this course offering.',
            ]);
        }

        $form = Form::query()
            ->where('type', 'survey')
            ->find($formId);

        if ($form === null) {
            throw ValidationException::withMessages([
                'form_id' => 'The selected form must be a survey.',
            ]);
        }

        return DB::transaction(function () use ($context, $form): FormTarget {
            $formTarget = $this->createFormTargetAction->execute([
                'form_id' => $form->id,
                'campus_id' => $context->campusId,
                'scope_type' => 'course',
                'scope_id' => $context->courseOfferingId,
                'semester_id' => $context->semesterId,
                'start_at' => now(),
                'end_at' => null,
                'status' => 'active',
                'is_mandatory' => true,
                'submission_limit_per_user' => 1,
            ]);

            $this->generateStudentAssignmentsAction->execute($formTarget);

            return $formTarget;
        });
    }

    private function hasSurveyForCourseOffering(int $courseOfferingId): bool
    {
        return FormTarget::query()
            ->where('scope_type', 'course')
            ->where('scope_id', $courseOfferingId)
            ->exists();
    }
}
