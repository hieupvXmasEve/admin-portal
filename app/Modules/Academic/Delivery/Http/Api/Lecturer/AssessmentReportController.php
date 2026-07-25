<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\ExportAssessmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\AssessmentExportService;
use App\Modules\Academic\Delivery\Support\AssessmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentReportController extends Controller
{
    public function __construct(
        private readonly AssessmentReportService $assessmentReportService,
        private readonly AssessmentExportService $assessmentExportService
    ) {}

    /**
     * Get overview statistics for assessment reporting.
     */
    public function overview(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        if ($response = $this->denyUnauthorizedCourse($courseOffering, $request)) {
            return $response;
        }

        try {
            $statistics = $this->assessmentReportService->generateOverviewStatistics($courseOffering);

            return ApiResponse::success([
                'course_offering' => [
                    'id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'course_title' => $courseOffering->course_title,
                    'section_code' => $courseOffering->section_code,
                    'semester' => [
                        'id' => $courseOffering->semester->id,
                        'name' => $courseOffering->semester->name,
                        'year' => $courseOffering->semester->year,
                    ],
                    'instructor' => $courseOffering->lecture ? [
                        'id' => $courseOffering->lecture->id,
                        'name' => $courseOffering->lecture->display_name,
                    ] : null,
                ],
                'statistics' => $statistics,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate overview statistics');
        }
    }

    /**
     * Get comprehensive grade matrix for all students and assessments.
     */
    public function gradeMatrix(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        if ($response = $this->denyUnauthorizedCourse($courseOffering, $request)) {
            return $response;
        }

        try {
            // Get filters from request
            $filters = [
                'include_excluded' => $request->boolean('include_excluded', false),
//                'score_status' => $request->input('score_status', 'final'),
//                'student_ids' => $request->input('student_ids', []),
//                'component_ids' => $request->input('component_ids', []),
            ];

            $gradeMatrix = $this->assessmentReportService->generateGradeMatrix($courseOffering, $filters);

            return ApiResponse::success([
                'course_offering' => [
                    'id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'course_title' => $courseOffering->course_title,
                    'section_code' => $courseOffering->section_code,
                ],
                'grade_matrix' => $gradeMatrix,
                'filters_applied' => $filters,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate grade matrix');
        }
    }

    /**
     * Get detailed analytics and statistics.
     */
    public function statistics(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        if ($response = $this->denyUnauthorizedCourse($courseOffering, $request)) {
            return $response;
        }

        try {
            $statisticsType = $request->input('type', 'all');
            $data = [];

            // Generate different types of statistics based on request
            switch ($statisticsType) {
                case 'overview':
                    $data['overview'] = $this->assessmentReportService->generateOverviewStatistics($courseOffering);
                    break;

                case 'score_distribution':
                    $data['score_distribution'] = $this->assessmentReportService->calculateScoreDistribution($courseOffering);
                    break;

                case 'completion':
                    $data['completion'] = $this->assessmentReportService->getCompletionStatistics($courseOffering);
                    break;

                case 'all':
                default:
                    $data['overview'] = $this->assessmentReportService->generateOverviewStatistics($courseOffering);
                    $data['score_distribution'] = $this->assessmentReportService->calculateScoreDistribution($courseOffering);
                    $data['completion'] = $this->assessmentReportService->getCompletionStatistics($courseOffering);
                    break;
            }

            return ApiResponse::success([
                'course_offering' => [
                    'id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'course_title' => $courseOffering->course_title,
                    'section_code' => $courseOffering->section_code,
                    'semester' => [
                        'id' => $courseOffering->semester->id,
                        'name' => $courseOffering->semester->name,
                        'year' => $courseOffering->semester->year,
                    ],
                ],
                'statistics' => $data,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to generate statistics');
        }
    }

    /**
     * Export assessment data to Excel format.
     */
    public function exportExcel(CourseOffering $courseOffering, ExportAssessmentRequest $request): BinaryFileResponse|JsonResponse
    {
        if ($response = $this->denyUnauthorizedCourse($courseOffering, $request)) {
            return $response;
        }

        try {
            // Get validated filters from request
            $filters = $request->getExcelFilters();

            // Generate Excel file
            $filePath = $this->assessmentExportService->exportToExcel($courseOffering, $filters);

            // Generate download filename
            $downloadName = 'assessment_export_' . $courseOffering->course_code . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to export to Excel');
        }
    }

    /**
     * Export assessment data to PDF format.
     */
    public function exportPdf(CourseOffering $courseOffering, ExportAssessmentRequest $request): BinaryFileResponse|JsonResponse
    {
        if ($response = $this->denyUnauthorizedCourse($courseOffering, $request)) {
            return $response;
        }

        try {
            // Get validated options from request
            $options = $request->getPdfOptions();

            // Generate PDF file
            $filePath = $this->assessmentExportService->exportToPdf($courseOffering, $options);

            // Generate download filename
            $downloadName = 'assessment_report_' . $courseOffering->course_code . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';

            // Return file download response
            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to export to PDF');
        }
    }

    private function denyUnauthorizedCourse(CourseOffering $courseOffering, Request $request): ?JsonResponse
    {
        return Gate::forUser($request->user())->denies('viewGradebook', $courseOffering)
            ? ApiResponse::authorizationError('Unauthorized access to course offering')
            : null;
    }
}
