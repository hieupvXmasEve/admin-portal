<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\UpdateAssessmentRequest;
use App\Http\Requests\StoreAssessmentDetailRequest;
use App\Http\Requests\UpdateAssessmentDetailRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Http\Requests\BulkUpdateGradesRequest;
use App\Http\Requests\ImportGradesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Student;
use App\Services\AssessmentManagementService;
use App\Services\AssessmentWeightValidationService;
use App\Services\AssessmentGradeExcelService;
use App\Http\Resources\Api\V1\WeightValidationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    /**
     * Get assessment structure for a course offering
     */
    public function index(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check if lecturer is authorized to access this course offering
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Use the AssessmentManagementService to get the complete structure
            $assessmentService = app(\App\Services\AssessmentManagementService::class);
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
            ], 'Assessment structure retrieved successfully');
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Create a new assessment component
     */
    public function store(StoreAssessmentRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            $syllabus = $courseOffering->syllabus;
            if (!$syllabus) {
                return ApiResponse::error(
                    'No syllabus found for this course offering',
                    [],
                    'SYLLABUS_NOT_FOUND',
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
            ], 'Assessment component created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Update an assessment component
     */
    public function update(UpdateAssessmentRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
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
            ], 'Assessment component updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Delete an assessment component
     */
    public function destroy(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
                    404
                );
            }

            // Enhanced dependency checking
            $dependencyChecks = $this->checkAssessmentDependencies($assessmentComponent, $courseOffering);

            if (!$dependencyChecks['can_delete']) {
                return ApiResponse::error(
                    'Cannot delete assessment component due to existing dependencies',
                    ['dependencies' => $dependencyChecks['dependencies']],
                    'HAS_DEPENDENCIES',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Create a new assessment component detail
     */
    public function storeDetail(StoreAssessmentDetailRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
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
            ], 'Assessment detail created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Update an assessment component detail
     */
    public function updateDetail(UpdateAssessmentDetailRequest $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent, AssessmentComponentDetail $assessmentDetail): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
                    404
                );
            }

            // Verify the assessment detail belongs to this assessment component
            if ($assessmentDetail->assessment_component_id !== $assessmentComponent->id) {
                return ApiResponse::error(
                    'Assessment detail does not belong to this assessment component',
                    [],
                    'INVALID_ASSESSMENT_DETAIL',
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
            ], 'Assessment detail updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Delete an assessment component detail
     */
    public function destroyDetail(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent, AssessmentComponentDetail $assessmentDetail): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component belongs to this course offering's syllabus
            if ($assessmentComponent->syllabus_id !== $courseOffering->syllabus?->id) {
                return ApiResponse::error(
                    'Assessment component does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
                    404
                );
            }

            // Verify the assessment detail belongs to this assessment component
            if ($assessmentDetail->assessment_component_id !== $assessmentComponent->id) {
                return ApiResponse::error(
                    'Assessment detail does not belong to this assessment component',
                    [],
                    'INVALID_ASSESSMENT_DETAIL',
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
                    'HAS_EXISTING_SCORES',
                    422
                );
            }

            // Use database transaction for safe deletion
            DB::transaction(function () use ($assessmentDetail) {
                $assessmentDetail->delete();
            });

            return ApiResponse::success(
                null,
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Get grading data by student
     */
    public function gradeByStudent(Request $request, CourseOffering $courseOffering, Student $student): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Use service to get grading data
            $assessmentService = app(AssessmentManagementService::class);
            $gradingData = $assessmentService->getGradingDataByStudent($courseOffering, $student);

            return ApiResponse::success(
                $gradingData,
                'Student grading data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve student grading data', [
                'course_offering_id' => $courseOffering->id,
                'student_code' => $student->id,
                'lecturer_id' => $lecturer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve student grading data',
                [],
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Get grading data by assessment component
     */
    public function gradeByComponent(Request $request, CourseOffering $courseOffering, AssessmentComponent $assessmentComponent): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Use service to get grading data
            $assessmentService = app(AssessmentManagementService::class);
            $gradingData = $assessmentService->getGradingDataByComponent($courseOffering, $assessmentComponent);

            return ApiResponse::success(
                $gradingData,
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Update a grade
     */
    public function updateGrade(UpdateGradeRequest $request, CourseOffering $courseOffering, AssessmentComponentDetailScore $score): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the score belongs to this course offering
            if ($score->course_offering_id !== $courseOffering->id) {
                return ApiResponse::error(
                    'Score does not belong to this course offering',
                    [],
                    'INVALID_SCORE',
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

            // Load fresh data with relationships
            $score->load('assessmentComponentDetail', 'student');

            // Use service to format response
            $assessmentService = app(AssessmentManagementService::class);
            $formattedScore = $assessmentService->formatScoreData($score);

            return ApiResponse::success(
                $formattedScore,
                'Grade updated successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Bulk update grades
     */
    public function bulkUpdateGrades(BulkUpdateGradesRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
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
                            'student_code' => $score->student_code,
                            'assessment_component_detail_id' => $score->assessment_component_detail_id
                        ];
                    } catch (\Exception $e) {
                        $errors[] = "Failed to update score ID {$scoreData['id']}: " . $e->getMessage();
                    }
                }
            });

            if (!empty($errors)) {
                return ApiResponse::error(
                    'Some grades could not be updated',
                    [
                        'bulk_errors' => $errors,
                        'successfully_updated' => count($updatedScores),
                        'failed_updates' => count($errors)
                    ],
                    'PARTIAL_FAILURE',
                    422
                );
            }

            return ApiResponse::success([
                'updated_scores' => $updatedScores,
                'total_updated' => count($updatedScores),
                'operation_completed_at' => now()->toISOString()
            ], 'All grades updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                'VALIDATION_ERROR',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Validate assessment weights for a specific course
     */
    public function validateWeights(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Use weight validation service
            $validationService = app(AssessmentWeightValidationService::class);
            $validationResult = $validationService->validateCourseWeights($courseOffering);

            return ApiResponse::success(
                new WeightValidationResource($validationResult),
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Export grade template for an assessment component detail
     */
    public function exportGradeTemplate(Request $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): BinaryFileResponse|JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (!$this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Import grades from Excel file for an assessment component detail
     */
    public function importGrades(ImportGradesRequest $request, CourseOffering $courseOffering, AssessmentComponentDetail $assessmentComponentDetail): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            // Check authorization
            if (!$this->canAccessCourseOffering($lecturer, $courseOffering)) {
                return ApiResponse::error(
                    'Unauthorized access to course offering',
                    [],
                    'UNAUTHORIZED',
                    403
                );
            }

            // Verify the assessment component detail belongs to this course offering
            if (!$this->assessmentBelongsToCourse($assessmentComponentDetail, $courseOffering)) {
                return ApiResponse::error(
                    'Assessment component detail does not belong to this course offering',
                    [],
                    'INVALID_ASSESSMENT',
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
            $hasErrors = !empty($importResults['errors']);
            $hasWarnings = !empty($importResults['warnings']);

            if ($hasErrors && $importResults['successful_updates'] === 0 && $importResults['successful_creates'] === 0) {
                return ApiResponse::error(
                    'Grade import failed',
                    $importResults,
                    'IMPORT_FAILED',
                    422
                );
            }

            if ($hasErrors || $hasWarnings) {
                return ApiResponse::success(
                    $importResults,
                    'Grade import completed with some issues'
                );
            }

            return ApiResponse::success(
                $importResults,
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
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Check if lecturer can access the course offering
     */
    private function canAccessCourseOffering($lecturer, CourseOffering $courseOffering): bool
    {
        return $courseOffering->lecture_id === $lecturer->id;
    }

    /**
     * Check if assessment component detail belongs to the course offering
     */
    private function assessmentBelongsToCourse(AssessmentComponentDetail $assessmentComponentDetail, CourseOffering $courseOffering): bool
    {
        return $assessmentComponentDetail->assessmentComponent
            && $assessmentComponentDetail->assessmentComponent->syllabus
            && $assessmentComponentDetail->assessmentComponent->syllabus->curriculum_unit_id === $courseOffering->curriculum_unit_id;
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
            $dependencies[] = "Assessment is published to students";
            // Allow deletion of published assessments but warn
        }

        // Check if assessment is in progress or completed
        if (in_array($assessmentComponent->status, ['in_progress', 'grading', 'completed'])) {
            $dependencies[] = "Assessment status is '{$assessmentComponent->status}'";
            $canDelete = false;
        }

        return [
            'can_delete' => $canDelete,
            'dependencies' => $dependencies
        ];
    }
}
