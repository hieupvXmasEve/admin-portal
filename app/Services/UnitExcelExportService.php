<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnitExcelExportService
{
    public function exportUnitsToExcel(array $filters = []): string
    {
        $units = $this->getUnitsWithRelationships($filters);

        $spreadsheet = new Spreadsheet();

        // Remove default worksheet
        $spreadsheet->removeSheetByIndex(0);

        // Create worksheets
        $this->createUnitsSummarySheet($spreadsheet, $units);
        $this->createPrerequisitesSheet($spreadsheet, $units);
        $this->createEquivalentsSheet($spreadsheet, $units);
        $this->createCurriculumMappingSheet($spreadsheet, $units);
        $this->createStatisticsSheet($spreadsheet);

        // Set active sheet to first one
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $fileName = 'units_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/temp/' . $fileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    public function getUnitsWithRelationships(array $filters = []): Collection
    {
        $query = Unit::with([
            'prerequisiteGroups.conditions.requiredUnit',
            'equivalentUnits.equivalentUnit',
            'curriculumUnits.curriculumVersion.program',
            'curriculumUnits.curriculumVersion.specialization',
            'syllabus'
        ]);

        $this->applyFilters($query, $filters);

        return $query->orderBy('code')->get();
    }

    private function createUnitsSummarySheet(Spreadsheet $spreadsheet, Collection $units): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Units Summary');

        // Headers
        $headers = [
            'Unit ID',
            'Code',
            'Name',
            'Credit Points',
            'Prerequisite Expression',
            'Prerequisites Count',
            'Equivalents Count',
            'Curricula Count',
            'Syllabus Count',
            'Created At',
            'Updated At'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($units as $unit) {
            $prerequisitesCount = $unit->prerequisiteGroups->sum(function ($group) {
                return $group->conditions->count();
            });
            $equivalentsCount = $unit->equivalentUnits->count();
            $curriculaCount = $unit->curriculumUnits->count();
            $syllabusCount = $unit->syllabus->count();

            // Generate human-readable prerequisite expression
            $prerequisiteExpression = $this->generatePrerequisiteExpression($unit);

            $worksheet->fromArray([
                $unit->id,
                $unit->code,
                $unit->name,
                $unit->credit_points,
                $prerequisiteExpression,
                $prerequisitesCount,
                $equivalentsCount,
                $curriculaCount,
                $syllabusCount,
                $unit->created_at->format('Y-m-d H:i:s'),
                $unit->updated_at->format('Y-m-d H:i:s')
            ], null, "A{$row}");

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'K') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:K' . ($row - 1));
    }

    /**
     * Generate a human-readable prerequisite expression from the unit's prerequisite groups
     */
    private function generatePrerequisiteExpression(Unit $unit): string
    {
        if ($unit->prerequisiteGroups->isEmpty()) {
            return 'None';
        }

        $groupExpressions = [];

        foreach ($unit->prerequisiteGroups as $group) {
            if ($group->conditions->isEmpty()) {
                continue;
            }

            $conditionExpressions = [];

            foreach ($group->conditions as $condition) {
                switch ($condition->type) {
                    case 'credit_requirement':
                        $conditionExpressions[] = "({$condition->required_credits}cps)";
                        break;
                    case 'prerequisite':
                        $prefix = '(P)';
                        $conditionExpressions[] = $prefix . $condition->requiredUnit?->code;
                        break;
                    case 'co_requisite':
                        $prefix = '(C)';
                        $conditionExpressions[] = $prefix . $condition->requiredUnit?->code;
                        break;
                    case 'anti_requisite':
                        $prefix = '(E)';
                        $conditionExpressions[] = $prefix . $condition->requiredUnit?->code;
                        break;
                    case 'textual':
                        $conditionExpressions[] = "({$condition->free_text})";
                        break;
                    default:
                        if ($condition->requiredUnit) {
                            $conditionExpressions[] = $condition->requiredUnit->code;
                        }
                        break;
                }
            }

            if (!empty($conditionExpressions)) {
                if (count($conditionExpressions) > 1) {
                    $groupExpression = '(' . implode(' ' . $group->logic_operator . ' ', $conditionExpressions) . ')';
                } else {
                    $groupExpression = $conditionExpressions[0];
                }
                $groupExpressions[] = $groupExpression;
            }
        }

        if (empty($groupExpressions)) {
            return 'None';
        }

        // If multiple groups, join them with AND (groups are typically combined with AND)
        if (count($groupExpressions) > 1) {
            return implode(' AND ', $groupExpressions);
        }

        return $groupExpressions[0];
    }

    private function createPrerequisitesSheet(Spreadsheet $spreadsheet, Collection $units): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Unit Prerequisites');

        // Headers
        $headers = [
            'Unit ID',
            'Unit Code',
            'Unit Name',
            'Group ID',
            'Group Logic',
            'Group Description',
            'Condition Type',
            'Required Unit Code',
            'Required Unit Name',
            'Required Credits',
            'Free Text'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($units as $unit) {
            foreach ($unit->prerequisiteGroups as $group) {
                foreach ($group->conditions as $condition) {
                    $worksheet->fromArray([
                        $unit->id,
                        $unit->code,
                        $unit->name,
                        $group->id,
                        $group->logic_operator,
                        $group->description,
                        $condition->type,
                        $condition->requiredUnit?->code ?? '',
                        $condition->requiredUnit?->name ?? '',
                        $condition->required_credits,
                        $condition->free_text
                    ], null, "A{$row}");

                    $row++;
                }
            }
        }

        // Auto-size columns
        foreach (range('A', 'K') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:K' . ($row - 1));
    }

    private function createEquivalentsSheet(Spreadsheet $spreadsheet, Collection $units): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Unit Equivalents');

        // Headers
        $headers = [
            'Unit ID',
            'Unit Code',
            'Unit Name',
            'Equivalent Unit ID',
            'Equivalent Unit Code',
            'Equivalent Unit Name',
            'Reason',
            'Valid From Semester',
            'Created At'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($units as $unit) {
            foreach ($unit->equivalentUnits as $equivalent) {
                $worksheet->fromArray([
                    $unit->id,
                    $unit->code,
                    $unit->name,
                    $equivalent->equivalentUnit->id,
                    $equivalent->equivalentUnit->code,
                    $equivalent->equivalentUnit->name,
                    $equivalent->reason,
                    $equivalent->validFromSemester?->term . ' ' . $equivalent->validFromSemester?->year ?? '',
                    $equivalent->created_at->format('Y-m-d H:i:s')
                ], null, "A{$row}");

                $row++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'I') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:I' . ($row - 1));
    }

    private function createCurriculumMappingSheet(Spreadsheet $spreadsheet, Collection $units): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Curriculum Mapping');

        // Headers
        $headers = [
            'Unit ID',
            'Unit Code',
            'Unit Name',
            'Program Name',
            'Specialization',
            'Curriculum Version',
            'Group Type',
            'Is Required',
            'Year Level',
            'Semester Number'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($units as $unit) {
            foreach ($unit->curriculumUnits as $curriculumUnit) {
                $worksheet->fromArray([
                    $unit->id,
                    $unit->code,
                    $unit->name,
                    $curriculumUnit->curriculumVersion->program->name,
                    $curriculumUnit->curriculumVersion->specialization?->name ?? 'N/A',
                    $curriculumUnit->curriculumVersion->version_code,
                    $curriculumUnit->group_type,
                    $curriculumUnit->is_required ? 'Yes' : 'No',
                    $curriculumUnit->year_level,
                    $curriculumUnit->semester_number
                ], null, "A{$row}");

                $row++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'J') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');

        // Add auto-filter
        $worksheet->setAutoFilter('A1:J' . ($row - 1));
    }

    private function createStatisticsSheet(Spreadsheet $spreadsheet): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Statistics');

        // Headers
        $headers = [
            'Metric',
            'Value',
            'Description'
        ];

        $worksheet->fromArray($headers, null, 'A1');

        // Apply header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $worksheet->getStyle('A1:C1')->applyFromArray($headerStyle);

        // Calculate statistics
        $totalUnits = Unit::count();
        $unitsWithPrerequisites = Unit::has('prerequisiteGroups')->count();
        $unitsWithEquivalents = Unit::has('equivalentUnits')->count();
        $unitsInCurricula = Unit::has('curriculumUnits')->count();
        $avgCreditPoints = Unit::avg('credit_points');

        $statistics = [
            ['Total Units', $totalUnits, 'Total number of units in the system'],
            ['Units with Prerequisites', $unitsWithPrerequisites, 'Units that have prerequisite requirements'],
            ['Units with Equivalents', $unitsWithEquivalents, 'Units that have equivalent unit relationships'],
            ['Units in Curricula', $unitsInCurricula, 'Units that are part of curriculum structures'],
            ['Average Credit Points', $avgCreditPoints ? round((float)$avgCreditPoints, 2) : 0, 'Average credit points across all units'],
        ];

        $row = 2;
        foreach ($statistics as $stat) {
            $worksheet->fromArray($stat, null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'C') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $worksheet->freezePane('A2');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['credit_points_from'])) {
            $query->where('credit_points', '>=', $filters['credit_points_from']);
        }

        if (isset($filters['credit_points_to'])) {
            $query->where('credit_points', '<=', $filters['credit_points_to']);
        }

        if (isset($filters['has_prerequisites']) && $filters['has_prerequisites']) {
            $query->has('prerequisiteGroups');
        }

        if (isset($filters['has_equivalents']) && $filters['has_equivalents']) {
            $query->has('equivalentUnits');
        }

        if (isset($filters['in_curricula']) && $filters['in_curricula']) {
            $query->has('curriculumUnits');
        }
    }
}
