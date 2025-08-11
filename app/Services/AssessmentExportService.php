<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AssessmentExportService implements WithMultipleSheets
{
    private CourseOffering $courseOffering;

    private array $filters;

    private AssessmentReportService $reportService;

    public function __construct(AssessmentReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Export assessment data to Excel format with proper formatting.
     */
    public function exportToExcel(CourseOffering $courseOffering, array $filters = []): string
    {
        $this->courseOffering = $courseOffering;
        $this->filters = array_merge([
            'include_excluded' => false,
            'score_status' => 'final',
            'student_ids' => [],
            'component_ids' => [],
            'include_statistics' => true,
            'include_grade_matrix' => true,
        ], $filters);

        // Create temporary file
        $fileName = 'assessment_export_' . $courseOffering->course_code . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        // Use Laravel Excel to export to the local disk
        Excel::store($this, 'temp/' . $fileName, 'local');

        // Return the actual file path where it was stored
        return Storage::disk('local')->path('temp/' . $fileName);
    }

    /**
     * Export assessment data to PDF format with charts and statistics.
     */
    public function exportToPdf(CourseOffering $courseOffering, array $options = []): string
    {
        $this->courseOffering = $courseOffering;
        $this->filters = array_merge([
            'include_charts' => true,
            'include_statistics' => true,
            'include_grade_matrix' => true,
            'page_orientation' => 'landscape',
            'include_student_details' => true,
        ], $options);

        // Create temporary file name
        $fileName = 'assessment_report_' . $courseOffering->course_code . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        // Create PDF export using Excel package with DOMPDF
        Excel::store(new PdfReportSheet($courseOffering, $this->reportService, $this->filters), 'temp/' . $fileName, 'local', \Maatwebsite\Excel\Excel::DOMPDF);

        // Return the actual file path where it was stored
        return Storage::disk('local')->path('temp/' . $fileName);
    }

    /**
     * Format export data for transformation.
     */
    public function formatExportData(SupportCollection $scores, array $components): array
    {
        $formattedData = [];

        foreach ($scores as $score) {
            $student = $score->student;
            $detail = $score->assessmentComponentDetail;
            $component = $detail->assessmentComponent;

            $formattedData[] = [
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'student_email' => $student->user->email,
                'student_number' => $student->student_number,
                'component_id' => $component->id,
                'component_name' => $component->name,
                'component_type' => $component->type,
                'component_weight' => $component->weight,
                'detail_id' => $detail->id,
                'detail_name' => $detail->name,
                'detail_weight' => $detail->weight,
                'points_earned' => $score->points_earned,
                'max_points' => $detail->max_points,
                'percentage_score' => $score->percentage_score,
                'letter_grade' => $score->letter_grade,
                'score_status' => $score->score_status,
                'is_late' => $score->is_late ? 'Yes' : 'No',
                'late_penalty_applied' => $score->late_penalty_applied,
                'bonus_points' => $score->bonus_points,
                'bonus_reason' => $score->bonus_reason,
                'score_excluded' => $score->score_excluded ? 'Yes' : 'No',
                'exclusion_reason' => $score->exclusion_reason,
                'plagiarism_suspected' => $score->plagiarism_suspected ? 'Yes' : 'No',
                'plagiarism_score' => $score->plagiarism_score,
                'appeal_requested' => $score->appeal_requested ? 'Yes' : 'No',
                'appeal_status' => $score->appeal_status,
                'graded_by' => $score->gradedByLecture?->display_name,
                'graded_at' => $score->graded_at?->format('Y-m-d H:i:s'),
                'instructor_feedback' => $score->instructor_feedback,
                'created_at' => $score->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $score->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        return $formattedData;
    }

    /**
     * Get sheets for Excel export.
     */
    public function sheets(): array
    {
        $sheets = [];

        // Always include grade matrix sheet
        if ($this->filters['include_grade_matrix']) {
            $gradeMatrix = $this->reportService->generateGradeMatrix($this->courseOffering, $this->filters);
            $sheets[] = new GradeMatrixSheet($gradeMatrix, $this->courseOffering);
        }

        // Include detailed scores sheet
        $detailedScores = $this->getDetailedScoresData();
        $sheets[] = new DetailedScoresSheet($detailedScores, $this->courseOffering);

        // Include statistics sheet if requested
        if ($this->filters['include_statistics']) {
            $statistics = $this->reportService->generateOverviewStatistics($this->courseOffering);
            $sheets[] = new StatisticsSheet($statistics, $this->courseOffering);
        }

        // Include component breakdown sheet
        $componentBreakdown = $this->getComponentBreakdownData();
        $sheets[] = new ComponentBreakdownSheet($componentBreakdown, $this->courseOffering);

        return $sheets;
    }

    /**
     * Get detailed scores data for export.
     */
    private function getDetailedScoresData(): SupportCollection
    {
        $syllabus = $this->courseOffering->syllabus;
        if (! $syllabus) {
            return collect();
        }

        $query = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail.assessmentComponent', function ($q) use ($syllabus) {
            $q->where('syllabus_id', $syllabus->id);
        })
            ->where('course_offering_id', $this->courseOffering->id)
            ->with([
                'student.user',
                'assessmentComponentDetail.assessmentComponent',
                'gradedByLecture',
            ]);

        // Apply filters
        if (! $this->filters['include_excluded']) {
            $query->where('score_excluded', false);
        }

        if (! empty($this->filters['score_status'])) {
            $query->where('score_status', $this->filters['score_status']);
        }

        if (! empty($this->filters['student_ids'])) {
            $query->whereIn('student_id', $this->filters['student_ids']);
        }

        if (! empty($this->filters['component_ids'])) {
            $query->whereHas('assessmentComponentDetail', function ($q) {
                $q->whereIn('assessment_component_id', $this->filters['component_ids']);
            });
        }

        return $query->orderBy('student_id')
            ->orderBy('assessment_component_detail_id')
            ->get();
    }

    /**
     * Get component breakdown data for export.
     */
    private function getComponentBreakdownData(): array
    {
        $syllabus = $this->courseOffering->syllabus;
        if (! $syllabus) {
            return [];
        }

        $components = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->with(['details'])
            ->get();

        $breakdown = [];
        foreach ($components as $component) {
            $statistics = $component->getGradingStatistics($this->courseOffering->id);
            $submissionCounts = $component->getSubmissionCounts($this->courseOffering->id);

            $breakdown[] = [
                'component' => $component,
                'statistics' => $statistics,
                'submission_counts' => $submissionCounts,
            ];
        }

        return $breakdown;
    }
}

/**
 * Grade Matrix Sheet for Excel export.
 */
class GradeMatrixSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private array $gradeMatrix;

    private CourseOffering $courseOffering;

    public function __construct(array $gradeMatrix, CourseOffering $courseOffering)
    {
        $this->gradeMatrix = $gradeMatrix;
        $this->courseOffering = $courseOffering;
    }

    public function collection()
    {
        if (empty($this->gradeMatrix['students'])) {
            return collect();
        }

        return collect($this->gradeMatrix['students'])->map(function ($student) {
            $row = [
                'student_id' => $student['id'],
                'student_name' => $student['name'],
                'student_number' => $student['student_number'] ?? '',
                'email' => $student['email'] ?? '',
            ];

            // Add component scores
            foreach ($this->gradeMatrix['components'] as $component) {
                $score = $student['component_scores'][$component['id']] ?? null;
                $row['component_' . $component['id']] = $score !== null ? $score : '';
            }

            // Add weighted total
            $row['weighted_total'] = $student['weighted_total'] ?? '';
            $row['letter_grade'] = $student['letter_grade'] ?? '';

            return $row;
        });
    }

    public function headings(): array
    {
        $headings = [
            'Student ID',
            'Student Name',
            'Student Number',
            'Email',
        ];

        // Add component headings
        foreach ($this->gradeMatrix['components'] as $component) {
            $headings[] = $component['name'] . ' (' . $component['weight'] . '%)';
        }

        $headings[] = 'Weighted Total (%)';
        $headings[] = 'Letter Grade';

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12, // Student ID
            'B' => 25, // Student Name
            'C' => 15, // Student Number
            'D' => 30, // Email
        ];

        // Component columns
        $componentCount = count($this->gradeMatrix['components'] ?? []);
        for ($i = 0; $i < $componentCount; $i++) {
            $column = chr(69 + $i); // Starting from E
            $widths[$column] = 12;
        }

        // Total and grade columns
        $totalColumn = chr(69 + $componentCount);
        $gradeColumn = chr(70 + $componentCount);
        $widths[$totalColumn] = 15;
        $widths[$gradeColumn] = 12;

        return $widths;
    }

    public function title(): string
    {
        return 'Grade Matrix';
    }
}

/**
 * Detailed Scores Sheet for Excel export.
 */
class DetailedScoresSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private SupportCollection $scores;

    private CourseOffering $courseOffering;

    public function __construct(SupportCollection $scores, CourseOffering $courseOffering)
    {
        $this->scores = $scores;
        $this->courseOffering = $courseOffering;
    }

    public function collection()
    {
        return $this->scores->map(function ($score) {
            return [
                'student_id' => $score->student->id,
                'student_name' => $score->student->user->name,
                'student_number' => $score->student->student_number ?? '',
                'student_email' => $score->student->user->email,
                'component_name' => $score->assessmentComponentDetail->assessmentComponent->name,
                'component_type' => $score->assessmentComponentDetail->assessmentComponent->type,
                'component_weight' => $score->assessmentComponentDetail->assessmentComponent->weight,
                'detail_name' => $score->assessmentComponentDetail->name,
                'detail_weight' => $score->assessmentComponentDetail->weight,
                'max_points' => $score->assessmentComponentDetail->max_points,
                'points_earned' => $score->points_earned ?? '',
                'percentage_score' => $score->percentage_score ?? '',
                'letter_grade' => $score->letter_grade ?? '',
                'score_status' => $score->score_status,
                'is_late' => $score->is_late ? 'Yes' : 'No',
                'late_penalty' => $score->late_penalty_applied ?? '',
                'bonus_points' => $score->bonus_points ?? '',
                'bonus_reason' => $score->bonus_reason ?? '',
                'excluded' => $score->score_excluded ? 'Yes' : 'No',
                'exclusion_reason' => $score->exclusion_reason ?? '',
                'plagiarism_suspected' => $score->plagiarism_suspected ? 'Yes' : 'No',
                'plagiarism_score' => $score->plagiarism_score ?? '',
                'appeal_requested' => $score->appeal_requested ? 'Yes' : 'No',
                'appeal_status' => $score->appeal_status ?? '',
                'graded_by' => $score->gradedByLecture?->display_name ?? '',
                'graded_at' => $score->graded_at?->format('Y-m-d H:i:s') ?? '',
                'instructor_feedback' => $score->instructor_feedback ?? '',
                'created_at' => $score->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $score->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Student Number',
            'Student Email',
            'Component Name',
            'Component Type',
            'Component Weight (%)',
            'Detail Name',
            'Detail Weight (%)',
            'Max Points',
            'Points Earned',
            'Percentage Score (%)',
            'Letter Grade',
            'Score Status',
            'Is Late',
            'Late Penalty',
            'Bonus Points',
            'Bonus Reason',
            'Excluded',
            'Exclusion Reason',
            'Plagiarism Suspected',
            'Plagiarism Score',
            'Appeal Requested',
            'Appeal Status',
            'Graded By',
            'Graded At',
            'Instructor Feedback',
            'Created At',
            'Updated At',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 25,
            'C' => 15,
            'D' => 30,
            'E' => 20,
            'F' => 15,
            'G' => 12,
            'H' => 20,
            'I' => 12,
            'J' => 12,
            'K' => 12,
            'L' => 12,
            'M' => 12,
            'N' => 12,
            'O' => 10,
            'P' => 12,
            'Q' => 12,
            'R' => 20,
            'S' => 10,
            'T' => 20,
            'U' => 15,
            'V' => 12,
            'W' => 15,
            'X' => 12,
            'Y' => 20,
            'Z' => 20,
            'AA' => 30,
            'AB' => 20,
            'AC' => 20,
        ];
    }

    public function title(): string
    {
        return 'Detailed Scores';
    }
}

/**
 * Statistics Sheet for Excel export.
 */
class StatisticsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private array $statistics;

    private CourseOffering $courseOffering;

    public function __construct(array $statistics, CourseOffering $courseOffering)
    {
        $this->statistics = $statistics;
        $this->courseOffering = $courseOffering;
    }

    public function collection()
    {
        $data = [];

        // Enrollment Statistics
        $data[] = ['Category', 'Metric', 'Value', ''];
        $data[] = ['Enrollment', 'Total Enrolled Students', $this->statistics['enrollment_statistics']['total_enrolled_students'], ''];
        $data[] = ['Enrollment', 'Active Students', $this->statistics['enrollment_statistics']['active_students'], ''];
        $data[] = ['', '', '', ''];

        // Assessment Structure
        $data[] = ['Assessment Structure', 'Total Components', $this->statistics['assessment_structure']['total_components'], ''];
        $data[] = ['Assessment Structure', 'Total Details', $this->statistics['assessment_structure']['total_details'], ''];
        $data[] = ['Assessment Structure', 'Total Weight', $this->statistics['assessment_structure']['total_weight'] . '%', ''];
        $data[] = ['Assessment Structure', 'Weight Complete', $this->statistics['assessment_structure']['weight_complete'] ? 'Yes' : 'No', ''];
        $data[] = ['', '', '', ''];

        // Score Statistics
        $data[] = ['Score Statistics', 'Average Score', $this->statistics['score_statistics']['average_score'] . '%', ''];
        $data[] = ['Score Statistics', 'Highest Score', $this->statistics['score_statistics']['highest_score'] . '%', ''];
        $data[] = ['Score Statistics', 'Lowest Score', $this->statistics['score_statistics']['lowest_score'] . '%', ''];
        $data[] = ['Score Statistics', 'Total Graded Submissions', $this->statistics['score_statistics']['total_graded_submissions'], ''];
        $data[] = ['', '', '', ''];

        // Completion Statistics
        $data[] = ['Completion', 'Completion Rate', $this->statistics['completion_statistics']['completion_rate'] . '%', ''];
        $data[] = ['Completion', 'Completed Assessments', $this->statistics['completion_statistics']['completed_assessments'], ''];
        $data[] = ['Completion', 'Pending Assessments', $this->statistics['completion_statistics']['pending_assessments'], ''];
        $data[] = ['Completion', 'Total Assessments', $this->statistics['completion_statistics']['total_assessments'], ''];
        $data[] = ['', '', '', ''];

        // Submission Statistics
        $data[] = ['Submissions', 'Submitted', $this->statistics['submission_statistics']['submitted'], ''];
        $data[] = ['Submissions', 'Graded', $this->statistics['submission_statistics']['graded'], ''];
        $data[] = ['Submissions', 'Late', $this->statistics['submission_statistics']['late'], ''];
        $data[] = ['Submissions', 'Plagiarism Flagged', $this->statistics['submission_statistics']['plagiarism_flagged'], ''];
        $data[] = ['Submissions', 'Appeals Requested', $this->statistics['submission_statistics']['appeals_requested'], ''];
        $data[] = ['Submissions', 'Excluded', $this->statistics['submission_statistics']['excluded'], ''];

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Category',
            'Metric',
            'Value',
            'Notes',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 25,
            'C' => 15,
            'D' => 30,
        ];
    }

    public function title(): string
    {
        return 'Statistics';
    }
}

/**
 * Component Breakdown Sheet for Excel export.
 */
class ComponentBreakdownSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private array $componentBreakdown;

    private CourseOffering $courseOffering;

    public function __construct(array $componentBreakdown, CourseOffering $courseOffering)
    {
        $this->componentBreakdown = $componentBreakdown;
        $this->courseOffering = $courseOffering;
    }

    public function collection()
    {
        return collect($this->componentBreakdown)->map(function ($item) {
            $component = $item['component'];
            $statistics = $item['statistics'];
            $submissionCounts = $item['submission_counts'];

            return [
                'component_id' => $component->id,
                'component_name' => $component->name,
                'component_type' => $component->type,
                'weight' => $component->weight,
                'details_count' => $component->details->count(),
                'average_score' => $statistics['average_score'] ?? 0,
                'highest_score' => $statistics['highest_score'] ?? 0,
                'lowest_score' => $statistics['lowest_score'] ?? 0,
                'total_submissions' => $submissionCounts['total'] ?? 0,
                'graded_submissions' => $submissionCounts['graded'] ?? 0,
                'pending_submissions' => $submissionCounts['pending'] ?? 0,
                'late_submissions' => $submissionCounts['late'] ?? 0,
                'excluded_submissions' => $submissionCounts['excluded'] ?? 0,
                'completion_rate' => $submissionCounts['completion_rate'] ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Component ID',
            'Component Name',
            'Type',
            'Weight (%)',
            'Details Count',
            'Average Score (%)',
            'Highest Score (%)',
            'Lowest Score (%)',
            'Total Submissions',
            'Graded Submissions',
            'Pending Submissions',
            'Late Submissions',
            'Excluded Submissions',
            'Completion Rate (%)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 25,
            'C' => 15,
            'D' => 12,
            'E' => 12,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
            'K' => 15,
            'L' => 15,
            'M' => 15,
            'N' => 15,
        ];
    }

    public function title(): string
    {
        return 'Component Breakdown';
    }
}

/**
 * PDF Report Sheet for generating PDF reports.
 */
class PdfReportSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private CourseOffering $courseOffering;

    private AssessmentReportService $reportService;

    private array $filters;

    public function __construct(CourseOffering $courseOffering, AssessmentReportService $reportService, array $filters = [])
    {
        $this->courseOffering = $courseOffering;
        $this->reportService = $reportService;
        $this->filters = $filters;
    }

    public function collection()
    {
        // Generate comprehensive report data for PDF
        $gradeMatrix = $this->reportService->generateGradeMatrix($this->courseOffering, $this->filters);
        $statistics = $this->reportService->generateOverviewStatistics($this->courseOffering);

        $data = [];

        // Course Information
        $data[] = ['ASSESSMENT REPORT', '', '', ''];
        $data[] = ['Course:', $this->courseOffering->course_code . ' - ' . $this->courseOffering->course_title, '', ''];
        $data[] = ['Section:', $this->courseOffering->section_code, '', ''];
        $data[] = ['Semester:', $this->courseOffering->semester->name . ' ' . $this->courseOffering->semester->year, '', ''];
        $data[] = ['Instructor:', $this->courseOffering->lecture?->display_name ?? 'Not assigned', '', ''];
        $data[] = ['Generated:', now()->format('F j, Y \a\t g:i A'), '', ''];
        $data[] = ['', '', '', ''];

        // Statistics Summary
        $data[] = ['STATISTICS SUMMARY', '', '', ''];
        $data[] = ['Enrolled Students:', $statistics['enrollment_statistics']['total_enrolled_students'], '', ''];
        $data[] = ['Assessment Components:', $statistics['assessment_structure']['total_components'], '', ''];
        $data[] = ['Total Weight:', $statistics['assessment_structure']['total_weight'] . '%', '', ''];
        $data[] = ['Average Score:', $statistics['score_statistics']['average_score'] . '%', '', ''];
        $data[] = ['Completion Rate:', $statistics['completion_statistics']['completion_rate'] . '%', '', ''];
        $data[] = ['', '', '', ''];

        // Grade Matrix Header
        if ($this->filters['include_grade_matrix'] && ! empty($gradeMatrix['students'])) {
            $data[] = ['GRADE MATRIX', '', '', ''];

            // Component headers
            $headerRow = ['Student Name'];
            foreach ($gradeMatrix['components'] as $component) {
                $headerRow[] = $component['name'] . ' (' . $component['weight'] . '%)';
            }
            $headerRow[] = 'Total (%)';
            $data[] = $headerRow;

            // Student data
            foreach ($gradeMatrix['students'] as $student) {
                $row = [$student['name']];
                foreach ($gradeMatrix['components'] as $component) {
                    $score = $student['component_scores'][$component['id']] ?? null;
                    $row[] = $score !== null ? number_format($score, 1) : '-';
                }
                $row[] = $student['weighted_total'] !== null ? number_format($student['weighted_total'], 1) : '-';
                $data[] = $row;
            }
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            9 => [
                'font' => ['bold' => true, 'size' => 14],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 15,
            'C' => 15,
            'D' => 15,
        ];
    }

    public function title(): string
    {
        return 'Assessment Report';
    }
}
