<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\StudentApplication;
use App\Modules\Upload\Models\ApplicationDocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Read-only export of Applications for the admissions CRM to complete.
 *
 * One row per Application: CRM matching keys first, then identity / contact /
 * intent / English-test, the lifecycle status, the primary Guardian, then one
 * column per active document type, then timestamps. **Missing values are left
 * blank** (not "N/A") so the CRM can see exactly which fields and documents are
 * absent and supply them — there is no in-app update path for frozen records.
 */
class StudentApplicationExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * Active document types (catalog order). Drives one column per type, so a
     * blank cell unambiguously means "this document type is missing".
     *
     * @var Collection<int, ApplicationDocumentType>
     */
    protected Collection $documentTypes;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(protected Builder $query, protected array $filters = [])
    {
        $this->documentTypes = ApplicationDocumentType::query()->activeOrdered()->get();
    }

    public function query()
    {
        return $this->query->with([
            'student:id,student_id,full_name',
            'guardians',
            'documents',
        ]);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            // CRM matching keys first.
            'Student Code',
            'CRM Admission ID',
            // Identity
            'Full Name',
            'Gender',
            'Ethnicity',
            'Date of Birth',
            'National ID',
            // Contact
            'Phone',
            'Email',
            'Address',
            'Health Information',
            // Admission intent
            'Campus',
            'Intended Program',
            'Intended Specialization',
            'Intake',
            // English test
            'Exam Date',
            'English Test Type',
            'Listening',
            'Reading',
            'Writing',
            'Speaking',
            'Overall Score',
            'StudyLink Status',
            'English Qualifications',
            'SUT ID',
            'International Applicant',
            'Exception Units',
            // Lifecycle
            'Application Status',
            'Linked Student Code',
            'Linked Student Name',
            // Primary guardian
            'Primary Guardian Name',
            'Primary Guardian Relationship',
            'Primary Guardian Phone',
            'Primary Guardian Email',
            // One column per active document type (blank = missing).
            ...$this->documentTypes->map(fn (ApplicationDocumentType $type): string => $type->name)->all(),
            // Timestamps
            'Created Date',
            'Updated Date',
        ];
    }

    /**
     * @param  StudentApplication  $application
     * @return list<string>
     */
    public function map($application): array
    {
        $guardian = $application->guardians->firstWhere('is_primary', true);

        return [
            $this->blank($application->student_code),
            $this->blank($application->crm_admission_id),
            $this->blank($application->full_name),
            $this->blank($application->gender),
            $this->blank($application->ethnicity),
            $this->formatBirthDate($application),
            $this->blank($application->national_id),
            $this->blank($application->phone),
            $this->blank($application->email),
            $this->blank($application->address),
            $this->blank($application->health_information),
            $this->blank($application->campus_code),
            $this->blank($application->intended_program),
            $this->blank($application->intended_specialization),
            $this->blank($application->intake),
            $application->exam_date ? $application->exam_date->format('Y-m-d') : '',
            $this->blank($application->english_test_type),
            $this->blank($application->listening),
            $this->blank($application->reading),
            $this->blank($application->writing),
            $this->blank($application->speaking),
            $this->blank($application->overall),
            $this->blank($application->study_link_status),
            $this->blank($application->english_qualifications),
            $this->blank($application->sut_id),
            $application->is_international_applicant ? 'Yes' : 'No',
            $this->blank($application->exception_units),
            ucfirst((string) $application->status),
            $this->blank($application->student?->student_id),
            $this->blank($application->student?->full_name),
            $this->blank($guardian?->full_name),
            $this->blank($guardian?->relationship),
            $this->blank($guardian?->phone),
            $this->blank($guardian?->email),
            ...$this->documentTypes->map(
                fn (ApplicationDocumentType $type): string => $this->documentsFor($application, $type->code)
            )->all(),
            $application->created_at ? $application->created_at->format('Y-m-d H:i:s') : '',
            $application->updated_at ? $application->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->freezePane('A2');

        return [];
    }

    /**
     * Normalize a value to a string, mapping null/empty to a blank cell so the
     * CRM sees the gap rather than a placeholder.
     */
    private function blank(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    /**
     * The file link(s) for one document type on an Application, newest page
     * first; blank when no document of that type has been submitted.
     */
    private function documentsFor(StudentApplication $application, string $code): string
    {
        return $application->documents
            ->where('file_type_code', $code)
            ->sortBy('page_index')
            ->map(fn ($document): string => (string) ($document->link ?: $document->original_name))
            ->filter()
            ->implode("\n");
    }

    private function formatBirthDate(StudentApplication $application): string
    {
        if (! $application->birth_day || ! $application->birth_month || ! $application->birth_year) {
            return '';
        }

        return sprintf(
            '%02d/%02d/%04d',
            $application->birth_day,
            $application->birth_month,
            $application->birth_year
        );
    }
}
