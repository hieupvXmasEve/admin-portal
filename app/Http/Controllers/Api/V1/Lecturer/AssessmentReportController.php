<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\ExportAssessmentRequest;
use App\Models\CourseOffering;
use App\Services\AssessmentExportService;
use App\Services\AssessmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    public function overview(CourseOffering $courseOffering): JsonResponse
    {
        // Check if user can access this course offering's assessments
        Gate::authorize('viewAssessments', $courseOffering);

        try {
            $statistics = $this->assessmentReportService->generateOverviewStatistics($courseOffering);

            return response()->json([
                'success' => true,
                'data' => [
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
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate overview statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive grade matrix for all students and assessments.
     */
    public function gradeMatrix(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        // Check if user can access this course offering's assessments
        Gate::authorize('viewAssessments', $courseOffering);

        try {
            // Get filters from request
            $filters = [
                'include_excluded' => $request->boolean('include_excluded', false),
                'score_status' => $request->input('score_status', 'final'),
                'student_ids' => $request->input('student_ids', []),
                'component_ids' => $request->input('component_ids', []),
            ];

            $gradeMatrix = $this->assessmentReportService->generateGradeMatrix($courseOffering, $filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'course_offering' => [
                        'id' => $courseOffering->id,
                        'course_code' => $courseOffering->course_code,
                        'course_title' => $courseOffering->course_title,
                        'section_code' => $courseOffering->section_code,
                    ],
                    'grade_matrix' => $gradeMatrix,
                    'filters_applied' => $filters,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate grade matrix',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed analytics and statistics.
     */
    public function statistics(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        // Check if user can access this course offering's assessments
        Gate::authorize('viewAssessments', $courseOffering);

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

            return response()->json([
                'success' => true,
                'data' => [
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
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export assessment data to Excel format.
     */
    public function exportExcel(CourseOffering $courseOffering, ExportAssessmentRequest $request): BinaryFileResponse
    {
        // Check if user can access this course offering's assessments
        Gate::authorize('viewAssessments', $courseOffering);

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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export to Excel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export assessment data to PDF format.
     */
    public function exportPdf(CourseOffering $courseOffering, ExportAssessmentRequest $request): BinaryFileResponse
    {
        // Check if user can access this course offering's assessments
        Gate::authorize('viewAssessments', $courseOffering);

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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export to PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
