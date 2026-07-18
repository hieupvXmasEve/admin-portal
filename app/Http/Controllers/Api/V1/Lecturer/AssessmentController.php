<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateGradesRequest;
use App\Http\Requests\GradeTableRequest;
use App\Http\Requests\ImportGradesRequest;
use App\Http\Requests\StoreAssessmentDetailRequest;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\UpdateAssessmentDetailRequest;
use App\Http\Requests\UpdateAssessmentRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Resources\Api\V1\WeightValidationResource;
use App\Http\Responses\ApiResponse;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Student;
use App\Modules\Academic\Delivery\Support\AssessmentManagementService;
use App\Modules\Academic\Delivery\Support\AssessmentWeightValidationService;
use App\Services\AssessmentGradeExcelService;
use App\Services\CourseCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentController extends Controller
{
    /**
     * Get assessment structure for a course offering
     */
    public function index(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check if lecturer is authorized to access this course offering
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Use the AssessmentManagementService to get the complete structure
            $assessmentService = app(AssessmentManagementService::class);
            $assessmentStructure = $assessmentService->getAssessmentStructure($courseOffering);

            return ApiResponse::success([
                'components' => $assessmentStructure['components'],
                'total_weight' => $assessmentStructure['total_weight'],
                'is_complete' => $assessmentStructure['is_complete'],
                'statistics' => $assessmentStructure['statistics'],
                'course_offering' => [
                    'id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'course_title' => $courseOffering->course_title,
                    'section_code' => $courseOffering->section_code,
                ],
            ], [], 'Assessment structure retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessment structure', [
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve assessment structure',
                [],
                500
            );
        }
    }

    /**
     * Create a new assessment component
     */
    public function store(StoreAssessmentRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            $syllabus = $courseOffering->syllabus;
            if (! $syllabus) {
                return ApiResponse::error(
                    'No syllabus found for this course offering',
                    [],
                    404
                );
            }

            // Get validated data and add syllabus_id
            $validated = $request->validated();
            $validated['syllabus_id'] = $syllabus->id;

            // Use service to create assessment component
            $assessmentService = app(AssessmentManagementService::class);
            $assessmentComponent = $assessmentService->createAssessmentComponent($validated);

            // Load relationships for response
            $assessmentComponent->load('details');

            return ApiResponse::success([
                'id' => $assessmentComponent->id,
                'name' => $assessmentComponent->name,
                'code' => $assessmentComponent->code,
                'description' => $assessmentComponent->description,
                'type' => $assessmentComponent->type,
                'type_name' => $assessmentComponent->type_name,
                'weight' => $assessmentComponent->weight,
                'is_required_to_sit_final_exam' => $assessmentComponent->is_required_to_sit_final_exam,
                'due_date' => $assessmentComponent->due_date?->toISOString(),
                'available_from' => $assessmentComponent->available_from?->toISOString(),
                'late_submission_deadline' => $assessmentComponent->late_submission_deadline?->toISOString(),
                'late_penalty_percentage' => $assessmentComponent->late_penalty_percentage,
                'late_penalty_type' => $assessmentComponent->late_penalty_type,
                'submission_type' => $assessmentComponent->submission_type,
                'allowed_file_types' => $assessmentComponent->allowed_file_types,
                'max_file_size_mb' => $assessmentComponent->max_file_size_mb,
                'max_submissions' => $assessmentComponent->max_submissions,
                'allow_resubmission' => $assessmentComponent->allow_resubmission,
                'is_group_work' => $assessmentComponent->is_group_work,
                'min_group_size' => $assessmentComponent->min_group_size,
                'max_group_size' => $assessmentComponent->max_group_size,
                'students_form_groups' => $assessmentComponent->students_form_groups,
                'assessment_criteria' => $assessmentComponent->assessment_criteria,
                'grading_instructions' => $assessmentComponent->grading_instructions,
                'is_published' => $assessmentComponent->is_published,
                'scores_published' => $assessmentComponent->scores_published,
                'is_extra_credit' => $assessmentComponent->is_extra_credit,
                'status' => $assessmentComponent->status,
                'sort_order' => $assessmentComponent->sort_order,
                'category' => $assessmentComponent->category,
                'details' => $assessmentComponent->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'name' => $detail->name,
                        'description' => $detail->description,
                        'weight' => $detail->weight,
                        'max_points' => $detail->max_points,
                        'due_date' => $detail->due_date?->toISOString(),
                    ];
                }),
                'total_detail_weight' => $assessmentComponent->total_detail_weight,
            ], [], 'Assessment component created successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to create assessment component', [
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to create assessment component',
                [],
                500
            );
        }
    }

    /**
     * Update an assessment component
     */
    public function update(UpdateAssessmentRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    404
                );
            }

            // Get validated data
            $validated = $request->validated();

            // Use service to update assessment component
            $assessmentService = app(AssessmentManagementService::class);
            $assessmentComponent = $assessmentService->updateAssessmentComponent($assessmentComponent, $validated);

            // Load relationships for response
            $assessmentComponent->load('details');

            return ApiResponse::success([
                'id' => $assessmentComponent->id,
                'name' => $assessmentComponent->name,
                'code' => $assessmentComponent->code,
                'description' => $assessmentComponent->description,
                'type' => $assessmentComponent->type,
                'type_name' => $assessmentComponent->type_name,
                'weight' => $assessmentComponent->weight,
                'is_required_to_sit_final_exam' => $assessmentComponent->is_required_to_sit_final_exam,
                'due_date' => $assessmentComponent->due_date?->toISOString(),
                'available_from' => $assessmentComponent->available_from?->toISOString(),
                'late_submission_deadline' => $assessmentComponent->late_submission_deadline?->toISOString(),
                'late_penalty_percentage' => $assessmentComponent->late_penalty_percentage,
                'late_penalty_type' => $assessmentComponent->late_penalty_type,
                'submission_type' => $assessmentComponent->submission_type,
                'allowed_file_types' => $assessmentComponent->allowed_file_types,
                'max_file_size_mb' => $assessmentComponent->max_file_size_mb,
                'max_submissions' => $assessmentComponent->max_submissions,
                'allow_resubmission' => $assessmentComponent->allow_resubmission,
                'is_group_work' => $assessmentComponent->is_group_work,
                'min_group_size' => $assessmentComponent->min_group_size,
                'max_group_size' => $assessmentComponent->max_group_size,
                'students_form_groups' => $assessmentComponent->students_form_groups,
                'assessment_criteria' => $assessmentComponent->assessment_criteria,
                'grading_instructions' => $assessmentComponent->grading_instructions,
                'is_published' => $assessmentComponent->is_published,
                'scores_published' => $assessmentComponent->scores_published,
                'is_extra_credit' => $assessmentComponent->is_extra_credit,
                'status' => $assessmentComponent->status,
                'sort_order' => $assessmentComponent->sort_order,
                'category' => $assessmentComponent->category,
                'details' => $assessmentComponent->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'name' => $detail->name,
                        'description' => $detail->description,
                        'weight' => $detail->weight,
                        'max_points' => $detail->max_points,
                        'due_date' => $detail->due_date?->toISOString(),
                    ];
                }),
                'total_detail_weight' => $assessmentComponent->total_detail_weight,
            ], [], 'Assessment component updated successfully');
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to update assessment component', [
                'assessment_component_id' => $assessmentComponent->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to update assessment component',
                [],
                500
            );
        }
    }

    /**
     * Delete an assessment component
     */
    public function destroy(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    404
                );
            }

            // Enhanced dependency checking
            $dependencyChecks = $this->checkAssessmentDependencies($assessmentComponent, $courseOffering);

            if (! $dependencyChecks['can_delete']) {
                return ApiResponse::error(
                    'Cannot delete assessment component due to existing dependencies',
                    ['dependencies' => $dependencyChecks['dependencies']],
                    422
                );
            }

            // Use database transaction for safe deletion
            DB::transaction(function () use ($assessmentComponent) {
                // Delete assessment component details first (cascade should handle this, but being explicit)
                $assessmentComponent->details()->delete();

                // Delete the assessment component
                $assessmentComponent->delete();
            });

            return ApiResponse::success(
                null,
                [],
                'Assessment component deleted successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment component', [
                'assessment_component_id' => $assessmentComponent->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to delete assessment component',
                [],
                500
            );
        }
    }

    /**
     * Create a new assessment component detail
     */
    public function storeDetail(StoreAssessmentDetailRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    404
                );
            }

            // Get validated data and add assessment_component_id
            $validated = $request->validated();
            $validated['assessment_component_id'] = $assessmentComponent->id;

            // Use service to create assessment detail
            $assessmentService = app(AssessmentManagementService::class);
            $assessmentDetail = $assessmentService->createAssessmentComponentDetail($validated);

            return ApiResponse::success([
                'id' => $assessmentDetail->id,
                'assessment_component_id' => $assessmentDetail->assessment_component_id,
                'name' => $assessmentDetail->name,
                'description' => $assessmentDetail->description,
                'due_date' => $assessmentDetail->due_date?->toISOString(),
                'max_points' => $assessmentDetail->max_points,
                'weight' => $assessmentDetail->weight,
                'created_at' => $assessmentDetail->created_at->toISOString(),
                'updated_at' => $assessmentDetail->updated_at->toISOString(),
            ], [], 'Assessment detail created successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to create assessment detail', [
                'assessment_component_id' => $assessmentComponent->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to create assessment detail',
                [],
                500
            );
        }
    }

    /**
     * Update an assessment component detail
     */
    public function updateDetail(UpdateAssessmentDetailRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent, AssessmentComponentDetail $assessmentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    404
                );
            }

            // Verify the assessment detail belongs to this assessment component
            if ($assessmentDetail->assessment_component_id !== $assessmentComponent->id) {
                return ApiResponse::error(
                    'Assessment detail does not belong to this assessment component',
                    [],
                    404
                );
            }

            // Get validated data
            $validated = $request->validated();

            // Use service to update assessment detail
            $assessmentService = app(AssessmentManagementService::class);
            $assessmentDetail = $assessmentService->updateAssessmentComponentDetail($assessmentDetail, $validated);

            return ApiResponse::success([
                'id' => $assessmentDetail->id,
                'assessment_component_id' => $assessmentDetail->assessment_component_id,
                'name' => $assessmentDetail->name,
                'description' => $assessmentDetail->description,
                'due_date' => $assessmentDetail->due_date?->toISOString(),
                'max_points' => $assessmentDetail->max_points,
                'weight' => $assessmentDetail->weight,
                'created_at' => $assessmentDetail->created_at->toISOString(),
                'updated_at' => $assessmentDetail->updated_at->toISOString(),
            ], [], 'Assessment detail updated successfully');
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to update assessment detail', [
                'assessment_detail_id' => $assessmentDetail->id,
                'assessment_component_id' => $assessmentComponent->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to update assessment detail',
                [],
                500
            );
        }
    }

    /**
     * Delete an assessment component detail
     */
    public function destroyDetail(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent, AssessmentComponentDetail $assessmentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    404
                );
            }

            // Verify the assessment detail belongs to this assessment component
            if ($assessmentDetail->assessment_component_id !== $assessmentComponent->id) {
                return ApiResponse::error(
                    'Assessment detail does not belong to this assessment component',
                    [],
                    404
                );
            }

            // Check for existing scores - prevent deletion if there are any scores
            $hasScores = AssessmentComponentDetailScore::where('assessment_component_detail_id', $assessmentDetail->id)
                ->where('course_offering_id', $courseOffering->id)
                ->exists();

            if ($hasScores) {
                return ApiResponse::error(
                    'Cannot delete assessment detail with existing student scores',
                    [],
                    422
                );
            }

            // Use database transaction for safe deletion
            DB::transaction(function () use ($assessmentDetail) {
                $assessmentDetail->delete();
            });

            return ApiResponse::success(
                null,
                [],
                'Assessment detail deleted successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment detail', [
                'assessment_detail_id' => $assessmentDetail->id,
                'assessment_component_id' => $assessmentComponent->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to delete assessment detail',
                [],
                500
            );
        }
    }

    /**
     * Get grading data by student
     */
    public function gradeByStudent(Request $request, CourseOffering $courseOffering, Student $student): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Use service to get grading data
            $assessmentService = app(AssessmentManagementService::class);
            $gradingData = $assessmentService->getGradingDataByStudent($courseOffering, $student);

            return ApiResponse::success(
                $gradingData,
                [],
                'Student grading data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve student grading data', [
                'course_offering_id' => $courseOffering->id,
                'student_id' => $student->student_id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve student grading data',
                [],
                500
            );
        }
    }

    /**
     * Get grading data by assessment component
     */
    public function gradeByComponent(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Use service to get grading data
            $assessmentService = app(AssessmentManagementService::class);
            $gradingData = $assessmentService->getGradingDataByComponent($courseOffering, $assessmentComponent);

            return ApiResponse::success(
                $gradingData,
                [],
                'Assessment component grading data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessment component grading data', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_id' => $assessmentComponent->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve assessment component grading data',
                [],
                500
            );
        }
    }

    /**
     * Update a grade
     */
    public function updateGrade(UpdateGradeRequest $request, CourseOffering $courseOffering, AssessmentComponentDetailScore $score): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the score belongs to this course offering
            if ($score->course_offering_id !== $courseOffering->id) {
                return ApiResponse::error(
                    'Score does not belong to this course offering',
                    [],
                    404
                );
            }

            // Get validated data
            $validated = $request->validated();

            // Add grading metadata
            $validated['graded_by_lecture_id'] = $lecturer->id;
            $validated['graded_at'] = now();
            $validated['last_modified_by_lecture_id'] = $lecturer->id;
            $validated['last_modified_at'] = now();

            // Update the score
            $score->update($validated);
            $this->aggregateManualGradesForOpenManualCourse($courseOffering);

            // Load fresh data with relationships
            $score->load('assessmentComponentDetail', 'student');

            // Use service to format response
            $assessmentService = app(AssessmentManagementService::class);
            $formattedScore = $assessmentService->formatScoreData($score);

            return ApiResponse::success(
                $formattedScore,
                [],
                'Grade updated successfully'
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to update grade', [
                'score_id' => $score->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to update grade',
                [],
                500
            );
        }
    }

    /**
     * Bulk update grades
     */
    public function bulkUpdateGrades(BulkUpdateGradesRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Get validated data (includes metadata added by the request class)
            $validated = $request->validated();

            $updatedScores = [];
            $errors = [];

            // Use database transaction for bulk operations
            DB::transaction(function () use ($validated, $courseOffering, &$updatedScores, &$errors) {
                foreach ($validated['scores'] as $scoreData) {
                    try {
                        $score = AssessmentComponentDetailScore::findOrFail($scoreData['id']);

                        // Verify the score belongs to this course offering (already validated in request)
                        if ($score->course_offering_id !== $courseOffering->id) {
                            $errors[] = "Score ID {$scoreData['id']} does not belong to this course offering";

                            continue;
                        }

                        // Remove ID from update data
                        $updateData = collect($scoreData)->except(['id'])->toArray();

                        // Update the score
                        $score->update($updateData);
                        $updatedScores[] = [
                            'id' => $score->id,
                            'student_id' => $score->student_id,
                            'assessment_component_detail_id' => $score->assessment_component_detail_id,
                        ];
                    } catch (\Exception $e) {
                        $errors[] = "Failed to update score ID {$scoreData['id']}: ".$e->getMessage();
                    }
                }
            });

            if (! empty($updatedScores)) {
                $this->aggregateManualGradesForOpenManualCourse($courseOffering);
            }

            if (! empty($errors)) {
                return ApiResponse::error(
                    'Some grades could not be updated',
                    [
                        'bulk_errors' => $errors,
                        'successfully_updated' => count($updatedScores),
                        'failed_updates' => count($errors),
                    ],
                    422
                );
            }

            return ApiResponse::success([
                'updated_scores' => $updatedScores,
                'total_updated' => count($updatedScores),
                'operation_completed_at' => now()->toISOString(),

            ], [], 'All grades updated successfully');
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to bulk update grades', [
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to bulk update grades',
                [],
                500
            );
        }
    }

    /**
     * Validate assessment weights for a specific course
     */
    public function validateWeights(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Use weight validation service
            $validationService = app(AssessmentWeightValidationService::class);
            $validationResult = $validationService->validateCourseWeights($courseOffering);

            return ApiResponse::success(
                new WeightValidationResource($validationResult),
                [],
                'Assessment weights validation completed successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to validate assessment weights', [
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to validate assessment weights',
                [],
                500
            );
        }
    }

    /**
     * Export grade template for an assessment component detail
     */
    public function exportGradeTemplate(Request $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): BinaryFileResponse|JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Use service to export grade template
            $excelService = app(AssessmentGradeExcelService::class);
            $filePath = $excelService->exportGradeTemplate($assessmentComponentDetail, $courseOffering);

            // Return file download response
            return response()->download($filePath, basename($filePath), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Failed to export grade template', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to export grade template',
                [],
                500
            );
        }
    }

    /**
     * Import grades from Excel file for an assessment component detail
     */
    public function importGrades(ImportGradesRequest $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Get validated data with defaults
            $validated = $request->validatedWithDefaults();
            $file = $request->file('file');

            // Use service to import grades
            $excelService = app(AssessmentGradeExcelService::class);
            $importResults = $excelService->importGrades(
                $assessmentComponentDetail,
                $courseOffering,
                $file,
                $validated,
                $lecturer->id
            );

            // Determine response based on results
            $hasErrors = ! empty($importResults['errors']);
            $hasWarnings = ! empty($importResults['warnings']);

            if ($hasErrors && $importResults['successful_updates'] === 0 && $importResults['successful_creates'] === 0) {
                return ApiResponse::error(
                    'Grade import failed',
                    $importResults,
                    422
                );
            }

            if ($hasErrors || $hasWarnings) {
                return ApiResponse::success(
                    $importResults,
                    [],
                    'Grade import completed with some issues'
                );
            }

            return ApiResponse::success(
                $importResults,
                [],
                'Grade import completed successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to import grades', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to import grades',
                [],
                500
            );
        }
    }

    /**
     * Check if lecturer can access the course offering
     * Uses class session based authorization - lecturer must have at least one class session assigned
     */
    private function canAccessCourseOffering($lecturer, CourseOffering $courseOffering): bool
    {
        return $courseOffering->classSessions()
            ->where('lecture_id', $lecturer->id)
            ->exists();
    }

    /**
     * Check if assessment component detail belongs to the course offering
     */
    private function assessmentBelongsToCourse(AssessmentComponentDetail $assessmentComponentDetail, CourseOffering $courseOffering): bool
    {
        $assessmentComponent = $assessmentComponentDetail->assessmentComponent;

        return $assessmentComponent !== null
            && (int) $assessmentComponent->syllabus_template_id === (int) $courseOffering->syllabus_template_id;
    }

    private function aggregateManualGradesForOpenManualCourse(CourseOffering $courseOffering): void
    {
        if ($courseOffering->is_canvas_synced) {
            return;
        }

        if (! in_array($courseOffering->course_status, ['not_started', 'in_progress'], true)) {
            return;
        }

        app(CourseCompletionService::class)->aggregateManualGrades($courseOffering);
    }

    /**
     * Get student grades table with sorting and filtering for an assessment detail.
     */
    public function getGradeTable(GradeTableRequest $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Get validated filters with defaults
            $filters = $request->validatedWithDefaults();

            // Use service to get grade table data
            $assessmentService = app(AssessmentManagementService::class);
            $gradeTableData = $assessmentService->getGradeTableData(
                $assessmentComponentDetail,
                $courseOffering,
                $filters
            );

            return ApiResponse::success(
                $gradeTableData,
                [],
                'Grade table data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve grade table data', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve grade table data',
                [],
                500
            );
        }
    }

    /**
     * Get grade statistics and distribution for an assessment detail.
     */
    public function getGradeStatistics(Request $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Use service to calculate statistics
            $assessmentService = app(AssessmentManagementService::class);
            $statistics = $assessmentService->calculateGradeStatistics(
                $assessmentComponentDetail,
                $courseOffering
            );

            return ApiResponse::success(
                $statistics,
                [],
                'Grade statistics calculated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to calculate grade statistics', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to calculate grade statistics',
                [],
                500
            );
        }
    }

    /**
     * Bulk create or update grades for an assessment detail.
     */
    public function bulkUpsertGrades(Request $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Validate request data
            $validated = $request->validate([
                'grades' => ['required', 'array', 'min:1'],
                'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
                'grades.*.points_earned' => ['sometimes', 'numeric', 'min:0'],
                'grades.*.percentage_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'grades.*.letter_grade' => ['sometimes', 'string', 'max:5'],
                'grades.*.status' => ['sometimes', 'string', Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned'])],
                'grades.*.score_status' => ['sometimes', 'string', Rule::in(['draft', 'provisional', 'final'])],
                'grades.*.instructor_feedback' => ['sometimes', 'string', 'max:1000'],
                'grades.*.private_notes' => ['sometimes', 'string', 'max:1000'],
            ]);

            // Use service to bulk upsert grades
            $assessmentService = app(AssessmentManagementService::class);
            $results = $assessmentService->bulkUpsertGrades(
                $assessmentComponentDetail,
                $courseOffering,
                $validated['grades'],
                $lecturer->id
            );

            if (! empty($results['created']) || ! empty($results['updated'])) {
                $this->aggregateManualGradesForOpenManualCourse($courseOffering);
            }

            // Check if there were any errors
            if (! empty($results['errors'])) {
                return ApiResponse::success(
                    $results,
                    [],
                    'Bulk grade operation completed with some errors'
                );
            }

            return ApiResponse::success(
                $results,
                [],
                'Bulk grade operation completed successfully'
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to bulk upsert grades', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to bulk upsert grades',
                [],
                500
            );
        }
    }

    /**
     * Export grades for an assessment detail.
     */
    public function exportGrades(Request $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): BinaryFileResponse|JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (! $this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (! $this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    400
                );
            }

            // Get format from request
            $format = $request->query('format', 'excel');

            if ($format === 'excel') {
                // Use existing Excel service for export
                $excelService = app(AssessmentGradeExcelService::class);
                $filePath = $excelService->exportGrades($assessmentComponentDetail, $courseOffering);

                return response()->download($filePath, basename($filePath), [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])->deleteFileAfterSend(true);
            } elseif ($format === 'csv') {
                // Export as CSV
                $assessmentService = app(AssessmentManagementService::class);
                $gradeData = $assessmentService->getGradeTableData(
                    $assessmentComponentDetail,
                    $courseOffering,
                    ['per_page' => 10000] // Get all records
                );

                // Convert to CSV format
                $csv = $this->convertGradesToCsv($gradeData['data']);
                $filename = "grades_{$assessmentComponentDetail->id}_".now()->format('Y-m-d_His').'.csv';

                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename={$filename}",
                ]);
            } else {
                return ApiResponse::error(
                    'Invalid export format. Supported formats: excel, csv',
                    [],
                    400
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to export grades', [
                'course_offering_id' => $courseOffering->id,
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to export grades',
                [],
                500
            );
        }
    }

    /**
     * Convert grade data to CSV format.
     */
    private function convertGradesToCsv(array $gradeData): string
    {
        $csv = "Student ID,Student Name,Email,Points Earned,Percentage Score,Letter Grade,Status,Score Status,Submitted At,Graded At,Is Late,Minutes Late,Late Penalty,Plagiarism Suspected,Score Excluded,Appeal Requested\n";

        foreach ($gradeData as $record) {
            if ($record['score_data']) {
                $score = $record['score_data'];
                $student = $record['student'];
                $submission = $record['submission_info'];
                $grading = $record['grading_info'];
                $flags = $record['flags'];

                $csv .= sprintf(
                    '"%s","%s","%s",%s,%s,"%s","%s","%s","%s","%s",%s,%s,%s,%s,%s,%s'."\n",
                    $student['student_id'],
                    $student['name'],
                    $student['email'],
                    $score['points_earned'] ?? '',
                    $score['percentage_score'] ?? '',
                    $score['letter_grade'] ?? '',
                    $score['status'] ?? '',
                    $score['score_status'] ?? '',
                    $submission['submitted_at'] ?? '',
                    $grading['graded_at'] ?? '',
                    $submission['is_late'] ? 'Yes' : 'No',
                    $submission['minutes_late'] ?? '0',
                    $submission['late_penalty_applied'] ?? '0',
                    $flags['plagiarism_suspected'] ? 'Yes' : 'No',
                    $flags['score_excluded'] ? 'Yes' : 'No',
                    $flags['appeal_requested'] ? 'Yes' : 'No'
                );
            } else {
                // Student without score
                $student = $record['student'];
                $csv .= sprintf(
                    '"%s","%s","%s",,,,"not_submitted",,,,,,,,,,'."\n",
                    $student['student_id'],
                    $student['name'],
                    $student['email']
                );
            }
        }

        return $csv;
    }

    /**
     * Check dependencies before deleting an assessment component
     */
    private function checkAssessmentDependencies(AssessmentComponent $assessmentComponent, CourseOffering $courseOffering): array
    {
        $dependencies = [];
        $canDelete = true;

        // Check for existing scores
        $scoresCount = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($assessmentComponent) {
            $query->where('assessment_component_id', $assessmentComponent->id);
        })->where('course_offering_id', $courseOffering->id)->count();

        if ($scoresCount > 0) {
            $dependencies[] = "Has {$scoresCount} student score(s)";
            $canDelete = false;
        }

        // Check for graded scores specifically
        $gradedScoresCount = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($query) use ($assessmentComponent) {
            $query->where('assessment_component_id', $assessmentComponent->id);
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('score_status', 'final')
            ->count();

        if ($gradedScoresCount > 0) {
            $dependencies[] = "Has {$gradedScoresCount} finalized grade(s)";
            $canDelete = false;
        }

        // Check if assessment is published
        if ($assessmentComponent->is_published) {
            $dependencies[] = 'Assessment is published to students';
            // Allow deletion of published assessments but warn
        }

        // Check if assessment is in progress or completed
        if (in_array($assessmentComponent->status, ['in_progress', 'grading', 'completed'])) {
            $dependencies[] = "Assessment status is '{$assessmentComponent->status}'";
            $canDelete = false;
        }

        return [
            'can_delete' => $canDelete,
            'dependencies' => $dependencies,
        ];
    }
}
