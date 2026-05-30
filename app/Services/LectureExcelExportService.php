<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\Lecture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

class LectureExcelExportService implements WithMultipleSheets
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function exportLecturersToExcel(array $filters = []): string
    {
        $this->filters = $filters;

        // Create temporary file
        $fileName = 'lecturers_export_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        // Use Laravel Excel to export to the local disk
        Excel::store($this, 'temp/'.$fileName, 'local');

        // Return the actual file path where it was stored
        $filePath = Storage::disk('local')->path('temp/'.$fileName);

        return $filePath;
    }

    public function sheets(): array
    {
        $lecturers = $this->getLecturersWithCampuses($this->filters);

        return [
            new LecturersSummarySheet($lecturers),
            new LecturersDetailedSheet($lecturers),
            new CampusLecturerOverviewSheet,
        ];
    }

    public function getLecturersWithCampuses(array $filters = []): Collection
    {
        $query = Lecture::with(['campus']);
        $this->applyFilters($query, $filters);

        return $query->orderByName()->get();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['campus_id'])) {
            $query->where('campus_id', $filters['campus_id']);
        }

        if (! empty($filters['employment_status']) && $filters['employment_status'] !== 'all') {
            $query->where('employment_status', $filters['employment_status']);
        }

        if (! empty($filters['employment_type']) && $filters['employment_type'] !== 'all') {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (! empty($filters['department']) && $filters['department'] !== 'all') {
            $query->where('department', $filters['department']);
        }

        if (! empty($filters['academic_rank']) && $filters['academic_rank'] !== 'all') {
            $query->where('academic_rank', $filters['academic_rank']);
        }

        if (isset($filters['available_for_assignment'])) {
            $query->where('is_available_for_assignment', $filters['available_for_assignment']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('employee_id', 'like', '%'.$filters['search'].'%')
                    ->orWhere('first_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('last_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%')
                    ->orWhere('department', 'like', '%'.$filters['search'].'%')
                    ->orWhere('specialization', 'like', '%'.$filters['search'].'%');
            });
        }

        $semesterId = $filters['semester_id'] ?? null;
        $unitType = $filters['unit_type'] ?? null;
        $hasSemesterFilter = $semesterId !== null && $semesterId !== '' && $semesterId !== 'all';
        $hasUnitTypeFilter = $unitType !== null && $unitType !== '' && $unitType !== 'all';

        if ($hasSemesterFilter || $hasUnitTypeFilter) {
            $query->whereHas('courseOfferings', function (Builder $courseOfferingQuery) use ($filters, $hasSemesterFilter, $semesterId, $hasUnitTypeFilter, $unitType) {
                if (! empty($filters['campus_id'])) {
                    $courseOfferingQuery->where('course_offerings.campus_id', $filters['campus_id']);
                }

                if ($hasSemesterFilter) {
                    $courseOfferingQuery->where('course_offerings.semester_id', (int) $semesterId);
                }

                if ($hasUnitTypeFilter) {
                    $courseOfferingQuery->whereHas('unit', function (Builder $unitQuery) use ($unitType) {
                        $unitQuery->where('units.unit_type', $unitType);
                    });
                }
            });
        }
    }
}

class LecturersSummarySheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private Collection $lecturers;

    public function __construct(Collection $lecturers)
    {
        $this->lecturers = $lecturers;
    }

    public function collection()
    {
        return $this->lecturers->map(function ($lecturer) {
            return [
                'employee_id' => $lecturer->employee_id,
                'full_name' => $lecturer->full_name,
                'email' => $lecturer->email,
                'campus' => $lecturer->campus->name ?? 'N/A',
                'department' => $lecturer->department ?? 'N/A',
                'academic_rank' => $lecturer->getAcademicRankLabel(),
                'employment_type' => ucfirst(str_replace('_', ' ', $lecturer->employment_type)),
                'employment_status' => $lecturer->getEmploymentStatusLabel(),
                'hire_date' => $lecturer->hire_date?->format('Y-m-d'),
                'years_of_service' => $lecturer->years_of_service,
                'is_active' => $lecturer->is_active ? 'Yes' : 'No',
                'available_for_assignment' => $lecturer->is_available_for_assignment ? 'Yes' : 'No',
                'can_teach_online' => $lecturer->can_teach_online ? 'Yes' : 'No',
                'current_course_load' => $lecturer->getCurrentCourseLoad(),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Full Name',
            'Email',
            'Campus',
            'Department',
            'Academic Rank',
            'Employment Type',
            'Employment Status',
            'Hire Date',
            'Years of Service',
            'Is Active',
            'Available for Assignment',
            'Can Teach Online',
            'Current Course Load',
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
            'A' => 15, // Employee ID
            'B' => 25, // Full Name
            'C' => 30, // Email
            'D' => 20, // Campus
            'E' => 20, // Department
            'F' => 20, // Academic Rank
            'G' => 18, // Employment Type
            'H' => 18, // Employment Status
            'I' => 12, // Hire Date
            'J' => 15, // Years of Service
            'K' => 10, // Is Active
            'L' => 20, // Available for Assignment
            'M' => 15, // Can Teach Online
            'N' => 18, // Current Course Load
        ];
    }

    public function title(): string
    {
        return 'Lecturers Summary';
    }
}

class LecturersDetailedSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private Collection $lecturers;

    public function __construct(Collection $lecturers)
    {
        $this->lecturers = $lecturers;
    }

    public function collection()
    {
        return $this->lecturers->map(function ($lecturer) {
            return [
                'employee_id' => $lecturer->employee_id,
                'title' => $lecturer->title,
                'first_name' => $lecturer->first_name,
                'last_name' => $lecturer->last_name,
                'email' => $lecturer->email,
                'phone' => $lecturer->phone,
                'mobile_phone' => $lecturer->mobile_phone,
                'campus_code' => $lecturer->campus->code ?? 'N/A',
                'department' => $lecturer->department,
                'faculty' => $lecturer->faculty,
                'specialization' => $lecturer->specialization,
                'expertise_areas' => is_array($lecturer->expertise_areas) ? implode(', ', $lecturer->expertise_areas) : $lecturer->expertise_areas,
                'academic_rank' => ucfirst(str_replace('_', ' ', $lecturer->academic_rank)),
                'highest_degree' => $lecturer->highest_degree,
                'degree_field' => $lecturer->degree_field,
                'alma_mater' => $lecturer->alma_mater,
                'graduation_year' => $lecturer->graduation_year,
                'hire_date' => $lecturer->hire_date?->format('Y-m-d'),
                'contract_start_date' => $lecturer->contract_start_date?->format('Y-m-d'),
                'contract_end_date' => $lecturer->contract_end_date?->format('Y-m-d'),
                'employment_type' => ucfirst(str_replace('_', ' ', $lecturer->employment_type)),
                'employment_status' => ucfirst(str_replace('_', ' ', $lecturer->employment_status)),
                'preferred_teaching_days' => is_array($lecturer->preferred_teaching_days) ? implode(', ', $lecturer->preferred_teaching_days) : $lecturer->preferred_teaching_days,
                'preferred_start_time' => $lecturer->preferred_start_time?->format('H:i'),
                'preferred_end_time' => $lecturer->preferred_end_time?->format('H:i'),
                'max_teaching_hours_per_week' => $lecturer->max_teaching_hours_per_week,
                'teaching_modalities' => is_array($lecturer->teaching_modalities) ? implode(', ', $lecturer->teaching_modalities) : $lecturer->teaching_modalities,
                'office_address' => $lecturer->office_address,
                'office_phone' => $lecturer->office_phone,
                'emergency_contact_name' => $lecturer->emergency_contact_name,
                'emergency_contact_phone' => $lecturer->emergency_contact_phone,
                'emergency_contact_relationship' => $lecturer->emergency_contact_relationship,
                'certifications' => is_array($lecturer->certifications) ? implode(', ', $lecturer->certifications) : $lecturer->certifications,
                'languages' => is_array($lecturer->languages) ? implode(', ', $lecturer->languages) : $lecturer->languages,
                'is_active' => $lecturer->is_active ? 'Yes' : 'No',
                'can_teach_online' => $lecturer->can_teach_online ? 'Yes' : 'No',
                'is_available_for_assignment' => $lecturer->is_available_for_assignment ? 'Yes' : 'No',
                'notes' => $lecturer->notes,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Title',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Mobile Phone',
            'Campus Code',
            'Department',
            'Faculty',
            'Specialization',
            'Expertise Areas',
            'Academic Rank',
            'Highest Degree',
            'Degree Field',
            'Alma Mater',
            'Graduation Year',
            'Hire Date',
            'Contract Start Date',
            'Contract End Date',
            'Employment Type',
            'Employment Status',
            'Preferred Teaching Days',
            'Preferred Start Time',
            'Preferred End Time',
            'Max Teaching Hours Per Week',
            'Teaching Modalities',
            'Office Address',
            'Office Phone',
            'Emergency Contact Name',
            'Emergency Contact Phone',
            'Emergency Contact Relationship',
            'Certifications',
            'Languages',
            'Hourly Rate',
            'Is Active',
            'Can Teach Online',
            'Is Available For Assignment',
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
            'A' => 15, // Employee ID
            'B' => 10, // Title
            'C' => 15, // First Name
            'D' => 15, // Last Name
            'E' => 30, // Email
            'F' => 15, // Phone
            'G' => 15, // Mobile Phone
            'H' => 15, // Campus Code
            'I' => 20, // Department
            'J' => 20, // Faculty
            'K' => 25, // Specialization
            'L' => 30, // Expertise Areas
            'M' => 20, // Academic Rank
            'N' => 20, // Highest Degree
            'O' => 25, // Degree Field
            'P' => 30, // Alma Mater
            'Q' => 15, // Graduation Year
            'R' => 12, // Hire Date
            'S' => 18, // Contract Start Date
            'T' => 16, // Contract End Date
            'U' => 18, // Employment Type
            'V' => 18, // Employment Status
            'W' => 25, // Preferred Teaching Days
            'X' => 18, // Preferred Start Time
            'Y' => 16, // Preferred End Time
            'Z' => 25, // Max Teaching Hours Per Week
            'AA' => 20, // Teaching Modalities
            'AB' => 30, // Office Address
            'AC' => 15, // Office Phone
            'AD' => 25, // Emergency Contact Name
            'AE' => 20, // Emergency Contact Phone
            'AF' => 25, // Emergency Contact Relationship
            'AG' => 30, // Certifications
            'AH' => 20, // Languages
            'AI' => 15, // Is Active
            'AJ' => 15, // Can Teach Online
            'AK' => 25, // Is Available For Assignment
            'AL' => 40, // Notes
        ];
    }

    public function title(): string
    {
        return 'Lecturers Detailed';
    }
}

class CampusLecturerOverviewSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function collection()
    {
        $campuses = Campus::withCount(['lectures'])->get();

        return $campuses->map(function ($campus) {
            // Get lecturer statistics for this campus
            $activeLecturers = Lecture::where('campus_id', $campus->id)->active()->count();
            $availableForAssignment = Lecture::where('campus_id', $campus->id)->availableForAssignment()->count();
            $fullTimeLecturers = Lecture::where('campus_id', $campus->id)->fullTime()->count();
            $partTimeLecturers = Lecture::where('campus_id', $campus->id)->partTime()->count();
            $contractLecturers = Lecture::where('campus_id', $campus->id)->contract()->count();

            // Get department breakdown
            $departments = Lecture::where('campus_id', $campus->id)
                ->whereNotNull('department')
                ->distinct()
                ->pluck('department')
                ->implode(', ');

            return [
                'campus_id' => $campus->id,
                'campus_name' => $campus->name,
                'campus_code' => $campus->code,
                'total_lecturers' => $campus->lectures_count,
                'active_lecturers' => $activeLecturers,
                'available_for_assignment' => $availableForAssignment,
                'full_time_lecturers' => $fullTimeLecturers,
                'part_time_lecturers' => $partTimeLecturers,
                'contract_lecturers' => $contractLecturers,
                'departments' => $departments ?: 'No departments assigned',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Campus ID',
            'Campus Name',
            'Campus Code',
            'Total Lecturers',
            'Active Lecturers',
            'Available for Assignment',
            'Full Time Lecturers',
            'Part Time Lecturers',
            'Contract Lecturers',
            'Departments',
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
            'A' => 12, // Campus ID
            'B' => 25, // Campus Name
            'C' => 15, // Campus Code
            'D' => 18, // Total Lecturers
            'E' => 18, // Active Lecturers
            'F' => 22, // Available for Assignment
            'G' => 20, // Full Time Lecturers
            'H' => 20, // Part Time Lecturers
            'I' => 18, // Contract Lecturers
            'J' => 40, // Departments
        ];
    }

    public function title(): string
    {
        return 'Campus Lecturer Overview';
    }
}
