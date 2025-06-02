<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitPrerequisiteGroup;
use App\Models\UnitPrerequisiteCondition;
use App\Models\EquivalentUnit;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnitExcelImportService
{
    private array $importResults = [];
    private array $errors = [];
    private array $warnings = [];
    private int $processedRows = 0;
    private int $successfulRows = 0;
    private int $failedRows = 0;
    private int $skippedRows = 0;

    public function importUnitsFromExcel(string $filePath, array $options = []): array
    {
        $this->resetCounters();

        try {
            // Validate file
            $this->validateImportFile($filePath);

            // Load spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $format = $this->detectFormat($spreadsheet);

            // Process based on format
            switch ($format) {
                case 'simple':
                    $this->processSimpleFormat($spreadsheet, $options);
                    break;
                case 'detailed':
                    $this->processDetailedFormat($spreadsheet, $options);
                    break;
                case 'complete':
                    $this->processCompleteFormat($spreadsheet, $options);
                    break;
                default:
                    throw new \Exception('Unable to detect import format');
            }

            return $this->generateImportReport();
        } catch (\Exception $e) {
            Log::error('Unit import failed: ' . $e->getMessage(), [
                'file' => $filePath,
                'options' => $options
            ]);

            throw $e;
        }
    }

    public function validateImportFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $fileSize = filesize($filePath);
        $maxSize = config('import.max_file_size', '10MB');
        $maxSizeBytes = $this->convertToBytes($maxSize);

        if ($fileSize > $maxSizeBytes) {
            throw new \Exception("File size exceeds maximum allowed size of {$maxSize}");
        }

        $allowedExtensions = config('import.allowed_extensions', ['xlsx', 'xls']);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('Invalid file format. Allowed formats: ' . implode(', ', $allowedExtensions));
        }

        return true;
    }

    public function previewImportData(string $filePath, int $previewRows = 10): array
    {
        $this->validateImportFile($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $format = $this->detectFormat($spreadsheet);

        $preview = [
            'format' => $format,
            'sheets' => [],
            'estimated_units' => 0
        ];

        foreach ($spreadsheet->getAllSheets() as $index => $worksheet) {
            $sheetData = $this->getSheetPreview($worksheet, $previewRows);
            $preview['sheets'][] = [
                'name' => $worksheet->getTitle(),
                'headers' => $sheetData['headers'],
                'data' => $sheetData['data'],
                'total_rows' => $sheetData['total_rows']
            ];
        }

        // Estimate number of units based on format
        $preview['estimated_units'] = $this->estimateUnitCount($preview, $format);

        return $preview;
    }

    private function processSimpleFormat($spreadsheet, array $options): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $this->worksheetToArray($worksheet);

        if (empty($data)) {
            throw new \Exception('No data found in the worksheet');
        }

        $headers = array_shift($data); // Remove header row
        $this->validateSimpleFormatHeaders($headers);

        DB::beginTransaction();

        try {
            foreach ($data as $rowIndex => $row) {
                $this->processedRows++;
                $actualRowNumber = $rowIndex + 2; // +2 because we removed headers and Excel is 1-indexed

                try {
                    $unitData = $this->mapSimpleFormatRow($headers, $row);
                    $this->processUnitRow($unitData, $actualRowNumber, $options);
                    $this->successfulRows++;
                } catch (\Exception $e) {
                    $this->failedRows++;
                    $this->errors[] = [
                        'row' => $actualRowNumber,
                        'error' => $e->getMessage(),
                        'data' => $row
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processDetailedFormat($spreadsheet, array $options): void
    {
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheets[$worksheet->getTitle()] = $this->worksheetToArray($worksheet);
        }

        if (!isset($sheets['Units'])) {
            throw new \Exception('Required sheet not found. Expected: Units');
        }

        DB::beginTransaction();

        try {
            // First, process units
            $this->processUnitsSheet($sheets['Units'], $options);

            // Then, process prerequisites if sheet exists
            if (isset($sheets['Prerequisites'])) {
                $this->processPrerequisitesSheet($sheets['Prerequisites'], $options);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processCompleteFormat($spreadsheet, array $options): void
    {
        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheets[$worksheet->getTitle()] = $this->worksheetToArray($worksheet);
        }

        if (!isset($sheets['Units'])) {
            throw new \Exception('Required sheet not found. Expected: Units');
        }

        DB::beginTransaction();

        try {
            // Process units first
            $this->processUnitsSheet($sheets['Units'], $options);

            // Then, process prerequisites if sheet exists
            if (isset($sheets['Prerequisites'])) {
                $this->processPrerequisitesSheet($sheets['Prerequisites'], $options);
            }

            // Finally, process equivalents if sheet exists
            if (isset($sheets['Equivalents'])) {
                $this->processEquivalentsSheet($sheets['Equivalents'], $options);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function createOrUpdateUnit(array $unitData, int $rowNumber, array $options): Unit
    {
        // Validate unit data
        $validator = Validator::make($unitData, [
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'credit_points' => 'required|numeric|min:0.25|max:999.99'
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        // Check if unit exists
        $existingUnit = Unit::where('code', $unitData['code'])->first();

        if ($existingUnit) {
            $duplicateHandling = $options['duplicate_handling'] ?? config('import.duplicate_handling', 'update');

            switch ($duplicateHandling) {
                case 'skip':
                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'Unit already exists, skipped'
                    ];
                    return $existingUnit;

                case 'error':
                    throw new \Exception('Unit with code already exists');

                case 'update':
                default:
                    $existingUnit->update([
                        'name' => $unitData['name'],
                        'credit_points' => $unitData['credit_points'],
                    ]);

                    $this->warnings[] = [
                        'row' => $rowNumber,
                        'message' => 'Unit already exists, updated information'
                    ];

                    return $existingUnit;
            }
        }

        // Create new unit
        return Unit::create([
            'code' => $unitData['code'],
            'name' => $unitData['name'],
            'credit_points' => $unitData['credit_points'],
        ]);
    }

    private function createPrerequisiteRelationship(string $unitCode, string $requiredUnitCode, string $type, int $rowNumber, array $options): void
    {
        // Find units
        $unit = Unit::where('code', $unitCode)->first();
        if (!$unit) {
            throw new \Exception("Unit with code '{$unitCode}' not found");
        }

        $requiredUnit = Unit::where('code', $requiredUnitCode)->first();
        if (!$requiredUnit) {
            throw new \Exception("Required unit with code '{$requiredUnitCode}' not found");
        }

        // Check if prerequisite group exists for this unit
        $group = UnitPrerequisiteGroup::where('unit_id', $unit->id)->first();
        if (!$group) {
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unit->id,
                'logic_operator' => 'AND',
                'description' => 'Imported prerequisites',
            ]);
        }

        // Check if condition already exists
        $existingCondition = UnitPrerequisiteCondition::where([
            'group_id' => $group->id,
            'type' => $type,
            'required_unit_id' => $requiredUnit->id
        ])->first();

        if ($existingCondition) {
            $this->warnings[] = [
                'row' => $rowNumber,
                'message' => "Prerequisite relationship already exists between {$unitCode} and {$requiredUnitCode}"
            ];
            return;
        }

        // Create prerequisite condition
        UnitPrerequisiteCondition::create([
            'group_id' => $group->id,
            'type' => $type,
            'required_unit_id' => $requiredUnit->id,
        ]);
    }

    private function createEquivalentRelationship(string $unitCode, string $equivalentUnitCode, ?string $reason, int $rowNumber, array $options): void
    {
        // Find units
        $unit = Unit::where('code', $unitCode)->first();
        if (!$unit) {
            throw new \Exception("Unit with code '{$unitCode}' not found");
        }

        $equivalentUnit = Unit::where('code', $equivalentUnitCode)->first();
        if (!$equivalentUnit) {
            throw new \Exception("Equivalent unit with code '{$equivalentUnitCode}' not found");
        }

        // Check if relationship already exists
        $existingEquivalent = EquivalentUnit::where([
            'unit_id' => $unit->id,
            'equivalent_unit_id' => $equivalentUnit->id
        ])->first();

        if ($existingEquivalent) {
            $this->warnings[] = [
                'row' => $rowNumber,
                'message' => "Equivalent relationship already exists between {$unitCode} and {$equivalentUnitCode}"
            ];
            return;
        }

        // Get current semester for valid_from_semester_id
        $currentSemester = Semester::orderBy('year', 'desc')
            ->orderBy('term', 'desc')
            ->first();

        // Create equivalent relationship
        EquivalentUnit::create([
            'unit_id' => $unit->id,
            'equivalent_unit_id' => $equivalentUnit->id,
            'reason' => $reason,
            'valid_from_semester_id' => $currentSemester?->id,
        ]);
    }

    private function detectFormat($spreadsheet): string
    {
        $sheetNames = [];
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $sheetNames[] = $worksheet->getTitle();
        }

        // Check for complete format (all relationship sheets)
        if (in_array('Units', $sheetNames) && in_array('Prerequisites', $sheetNames) && in_array('Equivalents', $sheetNames)) {
            return 'complete';
        }

        // Check for detailed format (units with prerequisites)
        if (in_array('Units', $sheetNames) && in_array('Prerequisites', $sheetNames)) {
            return 'detailed';
        }

        // Check first sheet headers to determine format
        $firstSheet = $spreadsheet->getActiveSheet();
        $headers = $this->getSheetHeaders($firstSheet);

        // Simple format: basic unit fields only
        if (in_array('Code', $headers) && in_array('Name', $headers) && in_array('Credit Points', $headers)) {
            return 'simple';
        }

        return 'unknown';
    }

    private function worksheetToArray(Worksheet $worksheet): array
    {
        return $worksheet->toArray(null, true, true, true);
    }

    private function getSheetHeaders(Worksheet $worksheet): array
    {
        $highestColumn = $worksheet->getHighestColumn();
        $headers = [];

        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[] = $worksheet->getCell($col . '1')->getValue();
        }

        return array_filter($headers); // Remove empty headers
    }

    private function getSheetPreview(Worksheet $worksheet, int $previewRows): array
    {
        $data = $this->worksheetToArray($worksheet);
        $headers = !empty($data) ? array_shift($data) : [];

        return [
            'headers' => $headers,
            'data' => array_slice($data, 0, $previewRows),
            'total_rows' => count($data)
        ];
    }

    private function estimateUnitCount(array $preview, string $format): int
    {
        foreach ($preview['sheets'] as $sheet) {
            if ($sheet['name'] === 'Units') {
                return $sheet['total_rows'];
            }
        }

        // For simple format, the first sheet contains units
        return $preview['sheets'][0]['total_rows'] ?? 0;
    }

    private function validateSimpleFormatHeaders(array $headers): void
    {
        $required = ['Code', 'Name', 'Credit Points'];

        // Clean headers by removing asterisks and trimming whitespace
        $cleanHeaders = array_map(function ($header) {
            return trim(str_replace('*', '', $header));
        }, $headers);

        $missing = array_diff($required, $cleanHeaders);

        if (!empty($missing)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missing));
        }
    }

    private function mapSimpleFormatRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            // Clean header by removing asterisks and other formatting characters
            $cleanHeader = trim(str_replace(['*', '(required)', '(optional)'], '', $header));
            $key = strtolower(str_replace(' ', '_', $cleanHeader));
            $mapped[$key] = $row[$index] ?? null;
        }
        return $mapped;
    }

    private function processUnitRow(array $unitData, int $rowNumber, array $options): void
    {
        // Create or update unit
        $this->createOrUpdateUnit($unitData, $rowNumber, $options);
    }

    private function processUnitsSheet(array $data, array $options): void
    {
        if (empty($data)) return;

        $headers = array_shift($data);

        foreach ($data as $rowIndex => $row) {
            $this->processedRows++;
            $actualRowNumber = $rowIndex + 2;

            try {
                $unitData = $this->mapSimpleFormatRow($headers, $row);
                $this->createOrUpdateUnit($unitData, $actualRowNumber, $options);
                $this->successfulRows++;
            } catch (\Exception $e) {
                $this->failedRows++;
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
    }

    private function processPrerequisitesSheet(array $data, array $options): void
    {
        if (empty($data)) return;

        $headers = array_shift($data);

        // Group prerequisites by unit and group description to create proper groups
        $groupedPrerequisites = [];

        foreach ($data as $rowIndex => $row) {
            $actualRowNumber = $rowIndex + 2;

            try {
                $prerequisiteData = $this->mapSimpleFormatRow($headers, $row);

                $unitCode = trim($prerequisiteData['unit_code'] ?? '');
                $groupLogic = trim($prerequisiteData['group_logic'] ?? 'AND');
                $groupDescription = trim($prerequisiteData['group_description'] ?? '');
                $conditionType = trim($prerequisiteData['condition_type'] ?? 'prerequisite');
                $requiredUnitCode = trim($prerequisiteData['required_unit_code'] ?? '');
                $requiredCredits = $prerequisiteData['required_credits'] ?? null;
                $freeText = trim($prerequisiteData['free_text'] ?? '');

                if (empty($unitCode)) {
                    $this->errors[] = [
                        'row' => $actualRowNumber,
                        'error' => 'Unit code is required',
                        'data' => $row
                    ];
                    continue;
                }

                // Create a unique group key based on unit code, logic, and description
                $groupKey = $unitCode . '|' . $groupLogic . '|' . $groupDescription;

                if (!isset($groupedPrerequisites[$groupKey])) {
                    $groupedPrerequisites[$groupKey] = [
                        'unit_code' => $unitCode,
                        'group_logic' => $groupLogic,
                        'group_description' => $groupDescription ?: 'Imported prerequisites',
                        'conditions' => []
                    ];
                }

                // Add this condition to the group
                $groupedPrerequisites[$groupKey]['conditions'][] = [
                    'type' => $conditionType,
                    'required_unit_code' => $requiredUnitCode,
                    'required_credits' => $requiredCredits ? (int)$requiredCredits : null,
                    'free_text' => $freeText,
                    'row_number' => $actualRowNumber
                ];
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }

        // Now process each group
        foreach ($groupedPrerequisites as $groupData) {
            try {
                $this->createPrerequisiteGroup(
                    $groupData['unit_code'],
                    $groupData['group_logic'],
                    $groupData['group_description'],
                    $groupData['conditions'],
                    $options
                );
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => 0, // Group level error
                    'error' => "Failed to create prerequisite group for {$groupData['unit_code']}: " . $e->getMessage(),
                    'data' => $groupData
                ];
            }
        }
    }

    private function createPrerequisiteGroup(string $unitCode, string $groupLogic, string $groupDescription, array $conditions, array $options): void
    {
        // Find the unit
        $unit = Unit::where('code', $unitCode)->first();
        if (!$unit) {
            throw new \Exception("Unit with code '{$unitCode}' not found");
        }

        // Create the prerequisite group
        $group = UnitPrerequisiteGroup::create([
            'unit_id' => $unit->id,
            'logic_operator' => strtoupper($groupLogic),
            'description' => $groupDescription,
        ]);

        // Create each condition in the group
        foreach ($conditions as $conditionData) {
            $this->createPrerequisiteCondition($group->id, $conditionData, $options);
        }
    }

    private function createPrerequisiteCondition(int $groupId, array $conditionData, array $options): void
    {
        $type = $conditionData['type'];
        $requiredUnitCode = $conditionData['required_unit_code'];
        $requiredCredits = $conditionData['required_credits'];
        $freeText = $conditionData['free_text'];
        $rowNumber = $conditionData['row_number'];

        // Validate condition type
        $validTypes = ['prerequisite', 'co_requisite', 'concurrent', 'anti_requisite', 'assumed_knowledge', 'credit_requirement', 'textual'];
        if (!in_array($type, $validTypes)) {
            throw new \Exception("Invalid condition type '{$type}' at row {$rowNumber}");
        }

        $conditionToCreate = [
            'group_id' => $groupId,
            'type' => $type,
        ];

        // Handle different condition types
        switch ($type) {
            case 'credit_requirement':
                if (empty($requiredCredits)) {
                    throw new \Exception("Required credits must be specified for credit_requirement type at row {$rowNumber}");
                }
                $conditionToCreate['required_credits'] = $requiredCredits;
                if (!empty($freeText)) {
                    $conditionToCreate['free_text'] = $freeText;
                }
                break;

            case 'textual':
                if (empty($freeText)) {
                    throw new \Exception("Free text must be specified for textual type at row {$rowNumber}");
                }
                $conditionToCreate['free_text'] = $freeText;
                break;

            case 'prerequisite':
            case 'co_requisite':
            case 'concurrent':
            case 'anti_requisite':
            case 'assumed_knowledge':
                if (empty($requiredUnitCode)) {
                    throw new \Exception("Required unit code must be specified for {$type} type at row {$rowNumber}");
                }

                $requiredUnit = Unit::where('code', $requiredUnitCode)->first();
                if (!$requiredUnit) {
                    throw new \Exception("Required unit with code '{$requiredUnitCode}' not found at row {$rowNumber}");
                }
                $conditionToCreate['required_unit_id'] = $requiredUnit->id;
                break;

            default:
                throw new \Exception("Unhandled condition type '{$type}' at row {$rowNumber}");
        }

        // Check if this exact condition already exists
        $existingCondition = UnitPrerequisiteCondition::where($conditionToCreate)->first();
        if ($existingCondition) {
            $this->warnings[] = [
                'row' => $rowNumber,
                'message' => "Prerequisite condition already exists, skipped"
            ];
            return;
        }

        // Create the condition
        UnitPrerequisiteCondition::create($conditionToCreate);
    }

    private function processEquivalentsSheet(array $data, array $options): void
    {
        if (empty($data)) return;

        $headers = array_shift($data);

        foreach ($data as $rowIndex => $row) {
            $actualRowNumber = $rowIndex + 2;

            try {
                $equivalentData = $this->mapSimpleFormatRow($headers, $row);

                $this->createEquivalentRelationship(
                    $equivalentData['unit_code'],
                    $equivalentData['equivalent_unit_code'],
                    $equivalentData['reason'] ?? null,
                    $actualRowNumber,
                    $options
                );
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
    }

    private function generateImportReport(): array
    {
        return [
            'summary' => [
                'total_rows' => $this->processedRows,
                'successful' => $this->successfulRows,
                'failed' => $this->failedRows,
                'skipped' => $this->skippedRows,
                'processing_time' => '0 seconds' // Will be calculated by controller
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    private function resetCounters(): void
    {
        $this->importResults = [];
        $this->errors = [];
        $this->warnings = [];
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->skippedRows = 0;
    }

    private function convertToBytes(string $size): int
    {
        $unit = strtoupper(substr($size, -2));
        $value = (int)substr($size, 0, -2);

        switch ($unit) {
            case 'KB':
                return $value * 1024;
            case 'MB':
                return $value * 1024 * 1024;
            case 'GB':
                return $value * 1024 * 1024 * 1024;
            default:
                return (int)$size;
        }
    }

    /**
     * Import units with syllabus data from combined Excel format
     */
    public function importCombinedUnitsWithSyllabus(string $filePath, array $options = []): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);

            // Track all created/updated records
            $results = [
                'units' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'prerequisites' => ['created' => 0, 'skipped' => 0],
                'equivalents' => ['created' => 0, 'skipped' => 0],
                'syllabus' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'assessment_components' => ['created' => 0, 'skipped' => 0],
                'assessment_details' => ['created' => 0, 'skipped' => 0],
                'errors' => [],
                'warnings' => []
            ];

            DB::beginTransaction();

            // Step 1: Import Units
            $unitsSheet = $spreadsheet->getSheetByName('Units');
            if ($unitsSheet) {
                $unitsResult = $this->importUnitsFromSheet($unitsSheet, $options);
                $results['units'] = $unitsResult['units'];
                $results['errors'] = array_merge($results['errors'], $unitsResult['errors']);
                $results['warnings'] = array_merge($results['warnings'], $unitsResult['warnings']);
            }

            // Step 2: Import Prerequisites
            if ($options['create_prerequisites'] ?? true) {
                $prereqSheet = $spreadsheet->getSheetByName('Prerequisites');
                if ($prereqSheet) {
                    $prereqResult = $this->importPrerequisitesFromSheet($prereqSheet);
                    $results['prerequisites'] = $prereqResult['prerequisites'];
                    $results['errors'] = array_merge($results['errors'], $prereqResult['errors']);
                    $results['warnings'] = array_merge($results['warnings'], $prereqResult['warnings']);
                }
            }

            // Step 3: Import Equivalents
            if ($options['create_equivalents'] ?? true) {
                $equivSheet = $spreadsheet->getSheetByName('Equivalents');
                if ($equivSheet) {
                    $equivResult = $this->importEquivalentsFromSheet($equivSheet);
                    $results['equivalents'] = $equivResult['equivalents'];
                    $results['errors'] = array_merge($results['errors'], $equivResult['errors']);
                    $results['warnings'] = array_merge($results['warnings'], $equivResult['warnings']);
                }
            }

            // Step 4: Import Syllabus
            $syllabusSheet = $spreadsheet->getSheetByName('Syllabus');
            if ($syllabusSheet) {
                $syllabusResult = $this->importSyllabusFromSheet($syllabusSheet, $options);
                $results['syllabus'] = $syllabusResult['syllabus'];
                $results['errors'] = array_merge($results['errors'], $syllabusResult['errors']);
                $results['warnings'] = array_merge($results['warnings'], $syllabusResult['warnings']);
            }

            // Step 5: Import Assessment Components
            $assessmentSheet = $spreadsheet->getSheetByName('Assessment Components');
            if ($assessmentSheet) {
                $assessmentResult = $this->importAssessmentComponentsFromSheet($assessmentSheet);
                $results['assessment_components'] = $assessmentResult['assessment_components'];
                $results['errors'] = array_merge($results['errors'], $assessmentResult['errors']);
                $results['warnings'] = array_merge($results['warnings'], $assessmentResult['warnings']);
            }

            // Step 6: Import Assessment Details
            $detailsSheet = $spreadsheet->getSheetByName('Assessment Details');
            if ($detailsSheet) {
                $detailsResult = $this->importAssessmentDetailsFromSheet($detailsSheet);
                $results['assessment_details'] = $detailsResult['assessment_details'];
                $results['errors'] = array_merge($results['errors'], $detailsResult['errors']);
                $results['warnings'] = array_merge($results['warnings'], $detailsResult['warnings']);
            }

            // Calculate totals for summary
            $totalCreated = $results['units']['created'] + $results['syllabus']['created'] +
                $results['assessment_components']['created'] + $results['assessment_details']['created'] +
                $results['prerequisites']['created'] + $results['equivalents']['created'];

            $totalUpdated = $results['units']['updated'] + $results['syllabus']['updated'];

            $totalSkipped = $results['units']['skipped'] + $results['syllabus']['skipped'] +
                $results['assessment_components']['skipped'] + $results['assessment_details']['skipped'] +
                $results['prerequisites']['skipped'] + $results['equivalents']['skipped'];

            DB::commit();

            return [
                'success' => true,
                'summary' => [
                    'total_created' => $totalCreated,
                    'total_updated' => $totalUpdated,
                    'total_skipped' => $totalSkipped,
                    'total_errors' => count($results['errors']),
                    'total_warnings' => count($results['warnings'])
                ],
                'details' => $results
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Combined import failed: ' . $e->getMessage());
        }
    }

    /**
     * Import units from the Units sheet
     */
    private function importUnitsFromSheet($worksheet, array $options): array
    {
        $results = ['units' => ['created' => 0, 'updated' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows); // Remove header row

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2; // Account for header row and 0-based index

            if (empty(array_filter($row))) continue; // Skip empty rows

            try {
                $unitCode = trim($row[0] ?? '');
                $unitName = trim($row[1] ?? '');
                $creditPoints = $row[2] ?? null;

                if (empty($unitCode) || empty($unitName)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code and name are required";
                    continue;
                }

                $existingUnit = Unit::where('code', $unitCode)->first();

                if ($existingUnit) {
                    if (($options['duplicate_handling'] ?? 'update') === 'skip') {
                        $results['units']['skipped']++;
                        continue;
                    } elseif ($options['duplicate_handling'] === 'error') {
                        $results['errors'][] = "Row {$actualRow}: Unit code '{$unitCode}' already exists";
                        continue;
                    } else {
                        // Update existing unit
                        $existingUnit->update([
                            'name' => $unitName,
                            'credit_points' => $creditPoints,
                        ]);
                        $results['units']['updated']++;
                    }
                } else {
                    // Create new unit
                    Unit::create([
                        'code' => $unitCode,
                        'name' => $unitName,
                        'credit_points' => $creditPoints,
                    ]);
                    $results['units']['created']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Import syllabus from the Syllabus sheet
     */
    private function importSyllabusFromSheet($worksheet, array $options): array
    {
        $results = ['syllabus' => ['created' => 0, 'updated' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows); // Remove header row

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            if (empty(array_filter($row))) continue;

            try {
                $unitCode = trim($row[0] ?? '');
                $version = trim($row[1] ?? '');
                $description = trim($row[2] ?? '');
                $totalHours = $row[3] ?? null;
                $hoursPerSession = $row[4] ?? null;
                $effectiveSemester = trim($row[5] ?? '');
                $isActive = strtoupper(trim($row[6] ?? '')) === 'TRUE';

                if (empty($unitCode)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code is required";
                    continue;
                }

                $unit = Unit::where('code', $unitCode)->first();
                if (!$unit) {
                    $results['errors'][] = "Row {$actualRow}: Unit '{$unitCode}' not found";
                    continue;
                }

                // Find semester if specified
                $semesterId = null;
                if (!empty($effectiveSemester)) {
                    $semester = \App\Models\Semester::where('name', $effectiveSemester)->first();
                    if ($semester) {
                        $semesterId = $semester->id;
                    } else {
                        $results['warnings'][] = "Row {$actualRow}: Semester '{$effectiveSemester}' not found";
                    }
                }

                // Check for existing syllabus
                $existingSyllabus = \App\Models\Syllabus::where('unit_id', $unit->id)
                    ->where('version', $version)
                    ->first();

                if ($existingSyllabus) {
                    if (($options['duplicate_handling'] ?? 'update') === 'skip') {
                        $results['syllabus']['skipped']++;
                        continue;
                    } elseif ($options['duplicate_handling'] === 'error') {
                        $results['errors'][] = "Row {$actualRow}: Syllabus version '{$version}' already exists for unit '{$unitCode}'";
                        continue;
                    } else {
                        // Update existing syllabus
                        $existingSyllabus->update([
                            'description' => $description ?: $existingSyllabus->description,
                            'total_hours' => $totalHours ?: $existingSyllabus->total_hours,
                            'hours_per_session' => $hoursPerSession ?: $existingSyllabus->hours_per_session,
                            'effective_from_semester_id' => $semesterId ?: $existingSyllabus->effective_from_semester_id,
                            'is_active' => $isActive,
                        ]);
                        $results['syllabus']['updated']++;
                    }
                } else {
                    // If marking as active, deactivate other syllabus for this unit
                    if ($isActive) {
                        \App\Models\Syllabus::where('unit_id', $unit->id)->update(['is_active' => false]);
                    }

                    // Create new syllabus
                    \App\Models\Syllabus::create([
                        'unit_id' => $unit->id,
                        'version' => $version,
                        'description' => $description,
                        'total_hours' => $totalHours,
                        'hours_per_session' => $hoursPerSession,
                        'effective_from_semester_id' => $semesterId,
                        'is_active' => $isActive,
                    ]);
                    $results['syllabus']['created']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Import assessment components from the Assessment Components sheet
     */
    private function importAssessmentComponentsFromSheet($worksheet): array
    {
        $results = ['assessment_components' => ['created' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows);

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            if (empty(array_filter($row))) continue;

            try {
                $unitCode = trim($row[0] ?? '');
                $syllabusVersion = trim($row[1] ?? '');
                $componentName = trim($row[2] ?? '');
                $weight = $row[3] ?? null;
                $type = trim($row[4] ?? '');
                $isRequired = strtoupper(trim($row[5] ?? '')) === 'TRUE';

                if (empty($unitCode) || empty($componentName) || empty($type)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code, component name, and type are required";
                    continue;
                }

                // Find the syllabus
                $unit = Unit::where('code', $unitCode)->first();
                if (!$unit) {
                    $results['errors'][] = "Row {$actualRow}: Unit '{$unitCode}' not found";
                    continue;
                }

                $syllabus = \App\Models\Syllabus::where('unit_id', $unit->id)
                    ->where('version', $syllabusVersion)
                    ->first();

                if (!$syllabus) {
                    $results['errors'][] = "Row {$actualRow}: Syllabus version '{$syllabusVersion}' not found for unit '{$unitCode}'";
                    continue;
                }

                // Validate assessment type
                if (!in_array($type, array_keys(\App\Models\AssessmentComponent::TYPES))) {
                    $results['errors'][] = "Row {$actualRow}: Invalid assessment type '{$type}'";
                    continue;
                }

                // Check for existing component
                $existingComponent = \App\Models\AssessmentComponent::where('syllabus_id', $syllabus->id)
                    ->where('name', $componentName)
                    ->first();

                if ($existingComponent) {
                    $results['assessment_components']['skipped']++;
                    $results['warnings'][] = "Row {$actualRow}: Assessment component '{$componentName}' already exists";
                    continue;
                }

                // Create new assessment component
                \App\Models\AssessmentComponent::create([
                    'syllabus_id' => $syllabus->id,
                    'name' => $componentName,
                    'weight' => $weight,
                    'type' => $type,
                    'is_required_to_sit_final_exam' => $isRequired,
                ]);
                $results['assessment_components']['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Import assessment details from the Assessment Details sheet
     */
    private function importAssessmentDetailsFromSheet($worksheet): array
    {
        $results = ['assessment_details' => ['created' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows);

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            if (empty(array_filter($row))) continue;

            try {
                $unitCode = trim($row[0] ?? '');
                $syllabusVersion = trim($row[1] ?? '');
                $componentName = trim($row[2] ?? '');
                $detailName = trim($row[3] ?? '');
                $weight = $row[4] ?? null;

                if (empty($unitCode) || empty($componentName) || empty($detailName)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code, component name, and detail name are required";
                    continue;
                }

                // Find the assessment component
                $unit = Unit::where('code', $unitCode)->first();
                if (!$unit) {
                    $results['errors'][] = "Row {$actualRow}: Unit '{$unitCode}' not found";
                    continue;
                }

                $syllabus = \App\Models\Syllabus::where('unit_id', $unit->id)
                    ->where('version', $syllabusVersion)
                    ->first();

                if (!$syllabus) {
                    $results['errors'][] = "Row {$actualRow}: Syllabus version '{$syllabusVersion}' not found for unit '{$unitCode}'";
                    continue;
                }

                $component = \App\Models\AssessmentComponent::where('syllabus_id', $syllabus->id)
                    ->where('name', $componentName)
                    ->first();

                if (!$component) {
                    $results['errors'][] = "Row {$actualRow}: Assessment component '{$componentName}' not found";
                    continue;
                }

                // Check for existing detail
                $existingDetail = \App\Models\AssessmentComponentDetail::where('component_id', $component->id)
                    ->where('name', $detailName)
                    ->first();

                if ($existingDetail) {
                    $results['assessment_details']['skipped']++;
                    $results['warnings'][] = "Row {$actualRow}: Assessment detail '{$detailName}' already exists";
                    continue;
                }

                // Create new assessment detail
                \App\Models\AssessmentComponentDetail::create([
                    'component_id' => $component->id,
                    'name' => $detailName,
                    'weight' => $weight,
                ]);
                $results['assessment_details']['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Import prerequisites from the Prerequisites sheet
     */
    private function importPrerequisitesFromSheet($worksheet): array
    {
        $results = ['prerequisites' => ['created' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows);

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            if (empty(array_filter($row))) continue;

            try {
                $unitCode = trim($row[0] ?? '');
                $requiredUnitCode = trim($row[1] ?? '');
                $type = strtolower(trim($row[2] ?? ''));
                $groupLogic = trim($row[3] ?? '') ?: 'AND';
                $description = trim($row[4] ?? '');

                if (empty($unitCode) || empty($requiredUnitCode) || empty($type)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code, required unit code, and type are required";
                    continue;
                }

                // Find units
                $unit = Unit::where('code', $unitCode)->first();
                $requiredUnit = Unit::where('code', $requiredUnitCode)->first();

                if (!$unit) {
                    $results['errors'][] = "Row {$actualRow}: Unit '{$unitCode}' not found";
                    continue;
                }

                if (!$requiredUnit) {
                    $results['errors'][] = "Row {$actualRow}: Required unit '{$requiredUnitCode}' not found";
                    continue;
                }

                // Validate type
                if (!in_array($type, ['prerequisite', 'co_requisite', 'anti_requisite'])) {
                    $results['errors'][] = "Row {$actualRow}: Invalid prerequisite type '{$type}'";
                    continue;
                }

                // Create or find prerequisite group
                $group = \App\Models\UnitPrerequisiteGroup::firstOrCreate([
                    'unit_id' => $unit->id,
                    'logic_operator' => $groupLogic,
                ], [
                    'description' => $description ?: "Imported prerequisites"
                ]);

                // Check for existing condition
                $existingCondition = \App\Models\UnitPrerequisiteCondition::where('group_id', $group->id)
                    ->where('required_unit_id', $requiredUnit->id)
                    ->where('type', $type)
                    ->first();

                if ($existingCondition) {
                    $results['prerequisites']['skipped']++;
                    continue;
                }

                // Create prerequisite condition
                \App\Models\UnitPrerequisiteCondition::create([
                    'group_id' => $group->id,
                    'type' => $type,
                    'required_unit_id' => $requiredUnit->id,
                ]);
                $results['prerequisites']['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Import equivalents from the Equivalents sheet
     */
    private function importEquivalentsFromSheet($worksheet): array
    {
        $results = ['equivalents' => ['created' => 0, 'skipped' => 0], 'errors' => [], 'warnings' => []];

        $rows = $worksheet->toArray();
        $headers = array_shift($rows);

        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            if (empty(array_filter($row))) continue;

            try {
                $unitCode = trim($row[0] ?? '');
                $equivalentUnitCode = trim($row[1] ?? '');
                $reason = trim($row[2] ?? '');
                $validFromSemester = trim($row[3] ?? '');

                if (empty($unitCode) || empty($equivalentUnitCode)) {
                    $results['errors'][] = "Row {$actualRow}: Unit code and equivalent unit code are required";
                    continue;
                }

                // Find units
                $unit = Unit::where('code', $unitCode)->first();
                $equivalentUnit = Unit::where('code', $equivalentUnitCode)->first();

                if (!$unit) {
                    $results['errors'][] = "Row {$actualRow}: Unit '{$unitCode}' not found";
                    continue;
                }

                if (!$equivalentUnit) {
                    $results['errors'][] = "Row {$actualRow}: Equivalent unit '{$equivalentUnitCode}' not found";
                    continue;
                }

                // Find semester if specified
                $semesterId = null;
                if (!empty($validFromSemester)) {
                    $semester = \App\Models\Semester::where('name', $validFromSemester)->first();
                    if ($semester) {
                        $semesterId = $semester->id;
                    } else {
                        $results['warnings'][] = "Row {$actualRow}: Semester '{$validFromSemester}' not found";
                    }
                }

                // Check for existing equivalent relationship
                $existingEquivalent = \App\Models\EquivalentUnit::where('unit_id', $unit->id)
                    ->where('equivalent_unit_id', $equivalentUnit->id)
                    ->first();

                if ($existingEquivalent) {
                    $results['equivalents']['skipped']++;
                    continue;
                }

                // Create equivalent relationship
                \App\Models\EquivalentUnit::create([
                    'unit_id' => $unit->id,
                    'equivalent_unit_id' => $equivalentUnit->id,
                    'reason' => $reason,
                    'valid_from_semester_id' => $semesterId,
                ]);
                $results['equivalents']['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$actualRow}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
