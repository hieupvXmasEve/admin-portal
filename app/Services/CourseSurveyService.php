<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Form;
use App\Models\FormSurvey;
use App\Models\FormVersion;
use App\Models\StudentFormSurvey;
use App\Models\CourseRegistration;
use App\Models\AcademicRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseSurveyService
{
    public function __construct(
        protected SystemConfigService $systemConfigService
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

        // Note: This method is called from CourseCompletionService during course finalization
        // The course_status will be updated to 'completed' after finalizeCourse() returns
        // So we don't check course_status here - we trust that if this method is called,
        // the course is being finalized/completed

        // Load unit to check if it's EGC type (exclude EGC units)
        $courseOffering->load('unit');
        if ($courseOffering->unit && $courseOffering->unit->unit_type === 'egc') {
            Log::info('Skipping survey attachment for EGC unit', [
                'course_offering_id' => $courseOffering->id,
                'unit_type' => $courseOffering->unit->unit_type,
            ]);
            return false;
        }

        // Get default form survey
        $defaultFormId = $this->systemConfigService->get('default_course_survey');
        if (! $defaultFormId) {
            Log::warning('No default course survey configured', [
                'course_offering_id' => $courseOffering->id,
            ]);
            return false;
        }

        // Get the form and its latest published version
        $form = Form::find($defaultFormId);
        if (! $form || $form->status !== 'active') {
            Log::warning('Default course survey form not found or not active', [
                'course_offering_id' => $courseOffering->id,
                'form_id' => $defaultFormId,
            ]);
            return false;
        }

        $formVersion = $form->latestPublishedVersion()->first();
        if (! $formVersion) {
            Log::warning('No published version found for default course survey form', [
                'course_offering_id' => $courseOffering->id,
                'form_id' => $defaultFormId,
            ]);
            return false;
        }

        return DB::transaction(function () use ($courseOffering, $form, $formVersion) {
            // Check if form_survey already exists (idempotent)
            $formSurvey = FormSurvey::where('course_offering_id', $courseOffering->id)->first();

            if (! $formSurvey) {
                // Create form_survey
                $formSurvey = FormSurvey::create([
                    'form_id' => $form->id,
                    'form_version_id' => $formVersion->id,
                    'course_offering_id' => $courseOffering->id,
                ]);

                Log::info('Form survey created for course offering', [
                    'form_survey_id' => $formSurvey->id,
                    'course_offering_id' => $courseOffering->id,
                    'form_id' => $form->id,
                ]);
            } else {
                Log::info('Form survey already exists for course offering', [
                    'form_survey_id' => $formSurvey->id,
                    'course_offering_id' => $courseOffering->id,
                ]);
            }

            // Get all students registered in the course
            // Use both CourseRegistration and AcademicRecord to get all enrolled students
            $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
                ->pluck('student_id')
                ->unique()
                ->toArray();

            // Also include students from AcademicRecord (in case they're enrolled but not in CourseRegistration)
            $academicRecordStudentIds = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->pluck('student_id')
                ->unique()
                ->toArray();

            // Merge and get unique student IDs
            $allStudentIds = array_unique(array_merge($registeredStudentIds, $academicRecordStudentIds));

            if (empty($allStudentIds)) {
                Log::warning('No students found for course offering', [
                    'course_offering_id' => $courseOffering->id,
                ]);
                return true; // Form survey created, but no students to assign
            }

            // Create student_form_surveys for each student (idempotent)
            $createdCount = 0;
            $existingCount = 0;

            foreach ($allStudentIds as $studentId) {
                $studentSurvey = StudentFormSurvey::firstOrCreate(
                    [
                        'form_survey_id' => $formSurvey->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'status' => 'not_started',
                    ]
                );

                if ($studentSurvey->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $existingCount++;
                }
            }

            Log::info('Student form surveys created/updated for course offering', [
                'form_survey_id' => $formSurvey->id,
                'course_offering_id' => $courseOffering->id,
                'total_students' => count($allStudentIds),
                'created' => $createdCount,
                'existing' => $existingCount,
            ]);

            return true;
        });
    }
}
