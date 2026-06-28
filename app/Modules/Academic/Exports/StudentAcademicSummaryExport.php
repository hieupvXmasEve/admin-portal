<?php

declare(strict_types=1);

namespace App\Modules\Academic\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Single-student academic-summary workbook backing the Hub's Export action.
 *
 * A pure presenter: it receives already-shaped data (identity, the graduation
 * contract from getGraduationData(), the cumulative GPA snapshot, and a
 * transcript of academic records) and lays it out as labelled sections. All
 * data gathering stays in the controller/service so this class stays trivial
 * to test by value.
 */
class StudentAcademicSummaryExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * Human-readable labels for the graduation requirement keys, mirroring the
     * Hub's GraduationTab so the export and the screen speak the same words.
     *
     * @var array<string, string>
     */
    private const REQUIREMENT_LABELS = [
        'core_credits' => 'Core Credits',
        'elective_credits' => 'Elective Credits',
        'internship' => 'Internship',
        'thesis' => 'Thesis/Capstone',
        'english_requirement' => 'English Proficiency',
    ];

    /**
     * @param  array<string, mixed>  $student  Identity/context (student_id, full_name, program, …).
     * @param  array<string, mixed>  $graduation  The getGraduationData() contract.
     * @param  array<string, mixed>|null  $cumulative  Cumulative GPA snapshot, or null when no GPA is finalized.
     * @param  array<int, array<string, mixed>>  $courses  Transcript rows (code, name, semester, credits, percentage, grade).
     */
    public function __construct(
        private readonly array $student,
        private readonly array $graduation,
        private readonly ?array $cumulative,
        private readonly array $courses,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            ['Student Academic Summary'],
            ['Student ID', $this->student['student_id'] ?? ''],
            ['Full Name', $this->student['full_name'] ?? ''],
            ['Program', $this->student['program'] ?? ''],
            ['Specialization', $this->student['specialization'] ?? ''],
            ['Campus', $this->student['campus'] ?? ''],
            ['Status', $this->titleCase((string) ($this->student['status'] ?? ''))],
            ['Intake', $this->student['intake'] ?? ''],
            ['Generated At', now()->format('Y-m-d H:i:s')],
            [],
            ...$this->academicStandingSection(),
            [],
            ...$this->graduationProgressSection(),
            [],
            ...$this->requirementsSection(),
            [],
            ...$this->transcriptSection(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        return [];
    }

    public function title(): string
    {
        return 'Academic Summary';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function academicStandingSection(): array
    {
        $gpa = $this->cumulative !== null
            ? number_format((float) ($this->cumulative['gpa'] ?? 0), 2)
            : 'N/A';

        $standing = $this->cumulative !== null && ! empty($this->cumulative['academic_standing'])
            ? ucfirst((string) $this->cumulative['academic_standing'])
            : 'N/A';

        $creditsEarned = $this->cumulative['credits_earned'] ?? ($this->graduation['credit_summary']['total_earned'] ?? 0);

        return [
            ['Academic Standing'],
            ['Cumulative GPA', $gpa],
            ['Standing', $standing],
            ['Credits Earned', $creditsEarned],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function graduationProgressSection(): array
    {
        $credit = $this->graduation['credit_summary'] ?? [];
        $status = $this->graduation['graduation_status'] ?? [];

        return [
            ['Graduation Progress'],
            ['Total Credits Required', $credit['total_required'] ?? 0],
            ['Credits Earned', $credit['total_earned'] ?? 0],
            ['Credits Remaining', $credit['remaining'] ?? 0],
            ['Completion', number_format((float) ($credit['completion_percentage'] ?? 0), 1).'%'],
            ['Ready to Graduate', ($status['ready_to_graduate'] ?? false) ? 'Yes' : 'No'],
            ['Risk Level', ucfirst((string) ($status['risk_level'] ?? 'low'))],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function requirementsSection(): array
    {
        $rows = [
            ['Graduation Requirements'],
            ['Requirement', 'Status', 'Earned', 'Required'],
        ];

        foreach ($this->graduation['requirements'] ?? [] as $key => $requirement) {
            $label = self::REQUIREMENT_LABELS[$key] ?? $this->titleCase((string) $key);
            $required = $requirement['required'] ?? null;

            if (is_bool($required)) {
                $earned = ($requirement['completed'] ?? false) ? 'Yes' : 'No';
                $requiredLabel = $required ? 'Required' : 'Optional';
            } else {
                $earned = $requirement['earned'] ?? 0;
                $requiredLabel = $required ?? 0;
            }

            $rows[] = [
                $label,
                $this->titleCase((string) ($requirement['status'] ?? '')),
                $earned,
                $requiredLabel,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function transcriptSection(): array
    {
        $rows = [
            ['Transcript'],
            ['No', 'Unit Code', 'Unit Name', 'Semester', 'Credits', 'Final %', 'Grade'],
        ];

        foreach (array_values($this->courses) as $index => $course) {
            $rows[] = [
                $index + 1,
                $course['code'] ?? '',
                $course['name'] ?? '',
                $course['semester'] ?? '',
                $course['credits'] ?? '',
                $course['percentage'] ?? '',
                $course['grade'] ?? '',
            ];
        }

        return $rows;
    }

    private function titleCase(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
