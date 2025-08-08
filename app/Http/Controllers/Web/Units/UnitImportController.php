<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Units;

use App\Http\Controllers\Controller;
use App\Services\UnitExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UnitImportController extends Controller
{
    public function __construct(
        private readonly UnitExcelImportService $importService
    ) {}

    public function showImportForm(): InertiaResponse
    {
        return Inertia::render('units/Import', [
            'maxFileSize' => config('import.max_file_size', '10MB'),
            'allowedExtensions' => config('import.allowed_extensions', ['xlsx', 'xls']),
            'availableFormats' => [
                'simple' => 'Simple Format (Units only)',
                'detailed' => 'Detailed Format (Units with Prerequisites)',
                'complete' => 'Complete Format (Units with all relationships)',
                'combined' => 'Combined Format (Units and Syllabus)',
            ],
        ]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        Log::info('Unit import upload request received', [
            'has_file' => $request->hasFile('file'),
            'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : 'N/A',
            'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : 'N/A',
            'content_length' => $request->header('Content-Length'),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:2048', // 2MB max to match PHP limits
            'duplicate_handling' => 'nullable|in:skip,update,error',
        ]);

        try {
            $file = $request->file('file');
            $filename = time().'_'.$file->getClientOriginalName();

            // Ensure the directory exists
            $uploadDir = storage_path('app/temp/imports');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            Log::info('Attempting unit file upload', [
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'upload_dir' => $uploadDir,
                'dir_exists' => is_dir($uploadDir),
                'dir_writable' => is_writable($uploadDir),
            ]);

            // Store file temporarily using direct path
            $fullPath = storage_path('app/temp/imports/'.$filename);

            try {
                $file->move(storage_path('app/temp/imports'), $filename);
                $path = 'temp/imports/'.$filename;

                Log::info('Unit file move completed', [
                    'full_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                ]);
            } catch (\Exception $moveException) {
                Log::error('Unit file move failed', [
                    'error' => $moveException->getMessage(),
                    'upload_dir' => storage_path('app/temp/imports'),
                    'filename' => $filename,
                ]);
                throw new \Exception('Failed to move uploaded file: '.$moveException->getMessage());
            }

            // Verify file was stored
            if (! file_exists($fullPath)) {
                throw new \Exception('Failed to store uploaded file - file not found after move');
            }

            Log::info('Unit file uploaded successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'full_path' => $fullPath,
                'file_size' => filesize($fullPath),
            ]);

            // Get preview data
            $preview = $this->importService->previewImportData($fullPath, 5);

            return response()->json([
                'success' => true,
                'file_path' => $path,
                'filename' => $file->getClientOriginalName(),
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            Log::error('Unit file upload failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file' => $request->file('file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'preview_rows' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $fullPath = storage_path('app/'.$request->file_path);
            $previewRows = $request->preview_rows ?? 10;

            $preview = $this->importService->previewImportData($fullPath, $previewRows);

            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            Log::error('Unit preview failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file_path' => $request->file_path,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function processImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'duplicate_handling' => 'nullable|in:skip,update,error',
            'create_prerequisites' => 'nullable|boolean',
            'create_equivalents' => 'nullable|boolean',
        ]);

        $startTime = microtime(true);

        try {
            $fullPath = storage_path('app/'.$request->file_path);

            Log::info('Processing unit import', [
                'file_path' => $request->file_path,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
            ]);

            // Check if file exists
            if (! file_exists($fullPath)) {
                throw new \Exception('Import file not found at: '.$fullPath);
            }

            $options = [
                'duplicate_handling' => $request->duplicate_handling ?? 'update',
                'create_prerequisites' => $request->create_prerequisites ?? false,
                'create_equivalents' => $request->create_equivalents ?? false,
            ];

            // Detect if this is a combined format by checking for syllabus-related sheets
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheetNames = array_map(fn ($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets());
            $isCombinedFormat = in_array('Syllabus', $sheetNames) ||
                in_array('Assessment Components', $sheetNames) ||
                in_array('Assessment Details', $sheetNames);

            if ($isCombinedFormat) {
                $result = $this->importService->importCombinedUnitsWithSyllabus($fullPath, $options);
            } else {
                $result = $this->importService->importUnitsFromExcel($fullPath, $options);
            }

            // Calculate processing time
            $processingTime = round(microtime(true) - $startTime, 2);
            $result['summary']['processing_time'] = $processingTime.' seconds';

            // Clean up temporary file
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Temporary unit file cleaned up', ['path' => $fullPath]);
            }

            // Log successful import
            Log::info('Unit import completed successfully', [
                'user_id' => Auth::id(),
                'summary' => $result['summary'],
            ]);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            // Clean up temporary file on error
            $fullPath = storage_path('app/'.$request->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Temporary unit file cleaned up after error', ['path' => $fullPath]);
            }

            Log::error('Unit import failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file_path' => $request->file_path,
                'options' => $request->only(['duplicate_handling', 'create_prerequisites', 'create_equivalents']),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function downloadTemplate(string $format): BinaryFileResponse
    {
        $templates = [
            'simple' => 'units_simple_template.xlsx',
            'detailed' => 'units_detailed_template.xlsx',
            'complete' => 'units_complete_template.xlsx',
            'combined' => 'units_syllabus_combined_template.xlsx',
        ];

        if (! isset($templates[$format])) {
            abort(404, 'Template not found');
        }

        $templatePath = resource_path('templates/import/'.$templates[$format]);

        if (! file_exists($templatePath)) {
            // Generate template on the fly if it doesn't exist
            $templatePath = $this->generateTemplate($format);
        }

        return response()->download($templatePath, $templates[$format], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function getImportHistory(): JsonResponse
    {
        // This would typically fetch from import_logs table
        // For now, return empty array as we haven't implemented the ImportLog model yet
        return response()->json([
            'success' => true,
            'history' => [],
        ]);
    }

    public function debug(): JsonResponse
    {
        return response()->json([
            'user_id' => Auth::id(),
            'storage_path' => storage_path('app/temp/imports'),
            'storage_exists' => is_dir(storage_path('app/temp/imports')),
            'storage_writable' => is_writable(storage_path('app/temp/imports')),
        ]);
    }

    private function generateTemplate(string $format): string
    {
        // This is a simplified template generation
        // In a real implementation, you'd use PhpSpreadsheet to create proper templates

        $templateDir = storage_path('app/temp/templates');
        if (! is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        $templatePath = $templateDir."/units_{$format}_template.xlsx";

        // Create a simple Excel file with headers based on format
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;

        switch ($format) {
            case 'simple':
                $this->createSimpleTemplate($spreadsheet);
                break;
            case 'detailed':
                $this->createDetailedTemplate($spreadsheet);
                break;
            case 'complete':
                $this->createCompleteTemplate($spreadsheet);
                break;
            case 'combined':
                $this->createCombinedTemplate($spreadsheet);
                break;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($templatePath);

        return $templatePath;
    }

    private function createSimpleTemplate($spreadsheet): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Units');

        $headers = ['Code*', 'Name*', 'Credit Points*'];
        $worksheet->fromArray($headers, null, 'A1');

        // Add sample data
        $sampleData = [
            ['CS101', 'Introduction to Computer Science', '12.5'],
            ['CS201', 'Data Structures and Algorithms', '12.5'],
            ['MATH101', 'Calculus I', '15.0'],
        ];

        $row = 2;
        foreach ($sampleData as $data) {
            $worksheet->fromArray($data, null, "A{$row}");
            $row++;
        }

        // Style headers
        $worksheet->getStyle('A1:C1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:C1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $worksheet->getStyle('A1:C1')->getFont()->getColor()->setRGB('FFFFFF');

        // Add borders to header and sample data
        $worksheet->getStyle('A1:C4')->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Add data validation for Credit Points column
        $validation = $worksheet->getDataValidation('C2:C100');
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle('Credit Points');
        $validation->setPrompt('Enter a numeric value (e.g., 12.5)');
        $validation->setErrorTitle('Invalid Credit Points');
        $validation->setError('Credit points must be a positive number');
        $validation->setOperator(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::OPERATOR_GREATERTHAN);
        $validation->setFormula1('0');

        // Auto-size columns
        foreach (range('A', 'C') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add instructions sheet
        $this->addInstructionsSheet($spreadsheet, 'simple');
    }

    private function createDetailedTemplate($spreadsheet): void
    {
        // Units sheet
        $unitsSheet = $spreadsheet->getActiveSheet();
        $unitsSheet->setTitle('Units');

        $unitHeaders = ['Code*', 'Name*', 'Credit Points*'];
        $unitsSheet->fromArray($unitHeaders, null, 'A1');

        // Sample unit data
        $unitsSheet->fromArray(['CS101', 'Introduction to Computer Science', '12.5'], null, 'A2');
        $unitsSheet->fromArray(['CS201', 'Data Structures', '12.5'], null, 'A3');
        $unitsSheet->fromArray(['BUS30010', 'Business Ethics', '12.5'], null, 'A4');
        $unitsSheet->fromArray(['BUS30024', 'Corporate Governance', '12.5'], null, 'A5');
        $unitsSheet->fromArray(['CS301', 'Advanced Algorithms', '12.5'], null, 'A6');

        // Prerequisites sheet
        $prereqSheet = $spreadsheet->createSheet();
        $prereqSheet->setTitle('Prerequisites');

        $prereqHeaders = [
            'Unit Code*',
            'Group Logic*',
            'Group Description',
            'Condition Type*',
            'Required Unit Code',
            'Required Credits',
            'Free Text',
        ];
        $prereqSheet->fromArray($prereqHeaders, null, 'A1');

        // Sample prerequisite data - demonstrating complex structures
        // Example 1: Simple prerequisite
        $prereqSheet->fromArray(['CS201', 'AND', 'Basic programming prerequisites', 'prerequisite', 'CS101', '', ''], null, 'A2');

        // Example 2: Credit requirement + unit prerequisite (similar to your example)
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'credit_requirement', '', '175', '175 credit points completed'], null, 'A3');
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'prerequisite', 'CS201', '', ''], null, 'A4');

        // Example 3: OR group for business units (similar to BUS30010 OR BUS30024)
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30010', '', ''], null, 'A5');
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30024', '', ''], null, 'A6');

        // Unit code validation removed to prevent Excel corruption
        // $this->addUnitCodeDropdownValidation($prereqSheet, 'A', 2, 100);

        // Add dropdown validation for Group Logic column (column B)
        $this->addGroupLogicDropdownValidation($prereqSheet, 'B', 2, 100);

        // Add dropdown validation for Type column (column D)
        $this->addTypeDropdownValidation($prereqSheet, 'D', 2, 100);

        // Style both sheets
        foreach ([$unitsSheet, $prereqSheet] as $sheet) {
            $highestColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$highestColumn}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->getColor()->setRGB('FFFFFF');

            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // Add instructions sheet
        $this->addInstructionsSheet($spreadsheet, 'detailed');
    }

    private function createCompleteTemplate($spreadsheet): void
    {
        // Units sheet
        $unitsSheet = $spreadsheet->getActiveSheet();
        $unitsSheet->setTitle('Units');

        $unitHeaders = ['Code*', 'Name*', 'Credit Points*'];
        $unitsSheet->fromArray($unitHeaders, null, 'A1');

        // Sample unit data
        $unitsSheet->fromArray(['CS101', 'Introduction to Computer Science', '12.5'], null, 'A2');
        $unitsSheet->fromArray(['CS201', 'Data Structures', '12.5'], null, 'A3');
        $unitsSheet->fromArray(['BUS30010', 'Business Ethics', '12.5'], null, 'A4');
        $unitsSheet->fromArray(['BUS30024', 'Corporate Governance', '12.5'], null, 'A5');
        $unitsSheet->fromArray(['CS301', 'Advanced Algorithms', '12.5'], null, 'A6');

        // Prerequisites sheet with improved structure
        $prereqSheet = $spreadsheet->createSheet();
        $prereqSheet->setTitle('Prerequisites');

        $prereqHeaders = [
            'Unit Code*',
            'Group Logic*',
            'Group Description',
            'Condition Type*',
            'Required Unit Code',
            'Required Credits',
            'Free Text',
        ];
        $prereqSheet->fromArray($prereqHeaders, null, 'A1');

        // Complex prerequisite examples
        $prereqSheet->fromArray(['CS201', 'AND', 'Basic programming prerequisites', 'prerequisite', 'CS101', '', ''], null, 'A2');
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'credit_requirement', '', '175', '175 credit points completed'], null, 'A3');
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'prerequisite', 'CS201', '', ''], null, 'A4');
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30010', '', ''], null, 'A5');
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30024', '', ''], null, 'A6');

        // Equivalents sheet
        $equivSheet = $spreadsheet->createSheet();
        $equivSheet->setTitle('Equivalents');

        $equivHeaders = ['Unit Code*', 'Equivalent Unit Code*', 'Reason', 'Valid From Semester'];
        $equivSheet->fromArray($equivHeaders, null, 'A1');

        // Sample equivalent data
        $equivSheet->fromArray(['CS101', 'COMP101', 'Course code change', 'Semester 1 2023'], null, 'A2');
        $equivSheet->fromArray(['CS201', 'COMP201', 'Course code change', 'Semester 1 2023'], null, 'A3');

        // Add dropdown validations (unit code validations removed to prevent Excel corruption)
        // $this->addUnitCodeDropdownValidation($prereqSheet, 'A', 2, 100);
        $this->addGroupLogicDropdownValidation($prereqSheet, 'B', 2, 100);
        $this->addTypeDropdownValidation($prereqSheet, 'D', 2, 100);

        // Add validations to equivalents sheet (unit code validations removed)
        // $this->addUnitCodeDropdownValidation($equivSheet, 'A', 2, 100);
        // $this->addUnitCodeDropdownValidation($equivSheet, 'B', 2, 100);

        // Style all sheets
        foreach ([$unitsSheet, $prereqSheet, $equivSheet] as $sheet) {
            $highestColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$highestColumn}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->getColor()->setRGB('FFFFFF');

            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // Add instructions sheet
        $this->addInstructionsSheet($spreadsheet, 'complete');
    }

    private function createCombinedTemplate($spreadsheet): void
    {
        // 1. Units sheet
        $unitsSheet = $spreadsheet->getActiveSheet();
        $unitsSheet->setTitle('Units');

        $unitHeaders = ['Code*', 'Name*', 'Credit Points*'];
        $unitsSheet->fromArray($unitHeaders, null, 'A1');

        // Sample unit data
        $unitsSheet->fromArray(['CS101', 'Introduction to Computer Science', '12.5'], null, 'A2');
        $unitsSheet->fromArray(['CS201', 'Data Structures and Algorithms', '12.5'], null, 'A3');
        $unitsSheet->fromArray(['MATH101', 'Calculus I', '15.0'], null, 'A4');

        // 2. Prerequisites sheet
        $prereqSheet = $spreadsheet->createSheet();
        $prereqSheet->setTitle('Prerequisites');

        $prereqHeaders = [
            'Unit Code*',
            'Group Logic*',
            'Group Description',
            'Condition Type*',
            'Required Unit Code',
            'Required Credits',
            'Free Text',
        ];
        $prereqSheet->fromArray($prereqHeaders, null, 'A1');

        // Sample prerequisite data - demonstrating complex structures
        // Example 1: Simple prerequisite
        $prereqSheet->fromArray(['CS201', 'AND', 'Basic programming prerequisites', 'prerequisite', 'CS101', '', ''], null, 'A2');

        // Example 2: Complex expression like "(P)175cps And ((E) BUS30010 OR BUS30024)"
        // This creates multiple groups for CS301:
        // Group 1 (AND): Credit requirement + unit prerequisite
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'credit_requirement', '', '175', '175 credit points completed'], null, 'A3');
        $prereqSheet->fromArray(['CS301', 'AND', 'Credit and unit requirements', 'prerequisite', 'CS201', '', ''], null, 'A4');

        // Group 2 (OR): Business ethics alternatives
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30010', '', ''], null, 'A5');
        $prereqSheet->fromArray(['CS301', 'OR', 'Business ethics requirement', 'prerequisite', 'BUS30024', '', ''], null, 'A6');

        // Add dropdown validations for Prerequisites sheet
        // $this->addUnitCodeDropdownValidation($prereqSheet, 'A', 2, 100);
        $this->addGroupLogicDropdownValidation($prereqSheet, 'B', 2, 100);
        $this->addTypeDropdownValidation($prereqSheet, 'D', 2, 100);

        // 3. Equivalents sheet
        $equivSheet = $spreadsheet->createSheet();
        $equivSheet->setTitle('Equivalents');

        $equivHeaders = ['Unit Code*', 'Equivalent Unit Code*', 'Reason', 'Valid From Semester'];
        $equivSheet->fromArray($equivHeaders, null, 'A1');

        // Sample equivalent data
        $equivSheet->fromArray(['CS101', 'PROG101', 'Same programming content', '2024-S1'], null, 'A2');

        // 4. Syllabus sheet
        $syllabusSheet = $spreadsheet->createSheet();
        $syllabusSheet->setTitle('Syllabus');

        $syllabusHeaders = [
            'Unit Code*',
            'Version',
            'Description',
            'Total Hours',
            'Hours Per Session',
            'Effective From Semester',
            'Is Active*',
        ];
        $syllabusSheet->fromArray($syllabusHeaders, null, 'A1');

        // Sample syllabus data
        $syllabusSheet->fromArray([
            'CS101',
            'v1.0',
            'Introduction to programming concepts and software development fundamentals',
            '120',
            '3',
            '2024-S1',
            'TRUE',
        ], null, 'A2');
        $syllabusSheet->fromArray([
            'CS201',
            'v2.1',
            'Advanced data structures, algorithms, and complexity analysis',
            '150',
            '4',
            '2024-S1',
            'TRUE',
        ], null, 'A3');

        // Add dropdown validation for Is Active column (column G)
        $this->addBooleanDropdownValidation($syllabusSheet, 'G', 2, 100);

        // 5. Assessment Components sheet
        $assessmentSheet = $spreadsheet->createSheet();
        $assessmentSheet->setTitle('Assessment Components');

        $assessmentHeaders = [
            'Unit Code*',
            'Syllabus Version',
            'Component Name*',
            'Weight*',
            'Type*',
            'Required for Final Exam*',
        ];
        $assessmentSheet->fromArray($assessmentHeaders, null, 'A1');

        // Sample assessment component data
        $assessmentSheet->fromArray(['CS101', 'v1.0', 'Final Exam', '50', 'exam', 'TRUE'], null, 'A2');
        $assessmentSheet->fromArray(['CS101', 'v1.0', 'Group Project', '30', 'project', 'FALSE'], null, 'A3');
        $assessmentSheet->fromArray(['CS101', 'v1.0', 'Weekly Quizzes', '20', 'quiz', 'FALSE'], null, 'A4');
        $assessmentSheet->fromArray(['CS201', 'v2.1', 'Portfolio', '40', 'assignment', 'TRUE'], null, 'A5');
        $assessmentSheet->fromArray(['CS201', 'v2.1', 'Practical Exam', '35', 'exam', 'TRUE'], null, 'A6');
        $assessmentSheet->fromArray(['CS201', 'v2.1', 'Class Participation', '25', 'other', 'FALSE'], null, 'A7');

        // Add dropdown validation for Assessment Components sheet
        $this->addAssessmentTypeDropdownValidation($assessmentSheet, 'E', 2, 100); // Type column
        $this->addBooleanDropdownValidation($assessmentSheet, 'F', 2, 100); // Required for Final Exam column

        // 6. Assessment Details sheet
        $detailsSheet = $spreadsheet->createSheet();
        $detailsSheet->setTitle('Assessment Details');

        $detailsHeaders = [
            'Unit Code*',
            'Syllabus Version',
            'Component Name*',
            'Detail Name*',
            'Weight',
        ];
        $detailsSheet->fromArray($detailsHeaders, null, 'A1');

        // Sample assessment detail data
        $detailsSheet->fromArray(['CS101', 'v1.0', 'Group Project', 'Proposal', '20'], null, 'A2');
        $detailsSheet->fromArray(['CS101', 'v1.0', 'Group Project', 'Implementation', '50'], null, 'A3');
        $detailsSheet->fromArray(['CS101', 'v1.0', 'Group Project', 'Presentation', '30'], null, 'A4');
        $detailsSheet->fromArray(['CS201', 'v2.1', 'Portfolio', 'Literature Review', '40'], null, 'A5');
        $detailsSheet->fromArray(['CS201', 'v2.1', 'Portfolio', 'Case Study Analysis', '35'], null, 'A6');
        $detailsSheet->fromArray(['CS201', 'v2.1', 'Portfolio', 'Reflection Essay', '25'], null, 'A7');

        // Style all sheets
        $sheets = [$unitsSheet, $prereqSheet, $equivSheet, $syllabusSheet, $assessmentSheet, $detailsSheet];
        foreach ($sheets as $sheet) {
            $highestColumn = $sheet->getHighestColumn();

            // Style headers
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$highestColumn}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->getColor()->setRGB('FFFFFF');

            // Add borders to sample data
            $lastRow = $sheet->getHighestRow();
            $sheet->getStyle("A1:{$highestColumn}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Auto-size columns
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // Add instructions sheet
        $this->addInstructionsSheet($spreadsheet, 'combined');

        // Set the Units sheet as the active sheet (first sheet)
        $spreadsheet->setActiveSheetIndex(0);
    }

    /**
     * Add dropdown validation for prerequisite type column
     */
    private function addTypeDropdownValidation($worksheet, string $column, int $startRow, int $endRow): void
    {
        $typeOptions = [
            'prerequisite',
            'co_requisite',
            'concurrent',
            'anti_requisite',
            'assumed_knowledge',
            'credit_requirement',
            'textual',
        ];

        try {
            $validation = $worksheet->getDataValidation($column.$startRow.':'.$column.$endRow);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('Invalid Condition Type')
                ->setError('Please select a valid condition type from the dropdown.')
                ->setPromptTitle('Condition Type')
                ->setPrompt('Select the type of prerequisite condition.')
                ->setFormula1('"'.implode(',', $typeOptions).'"');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to add type validation: '.$e->getMessage());
        }
    }

    /**
     * Add dropdown validation for group logic column
     */
    private function addGroupLogicDropdownValidation($worksheet, string $column, int $startRow, int $endRow): void
    {
        $logicOptions = ['AND', 'OR'];

        try {
            $validation = $worksheet->getDataValidation($column.$startRow.':'.$column.$endRow);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('Invalid Logic Operator')
                ->setError('Please select either AND or OR.')
                ->setPromptTitle('Group Logic')
                ->setPrompt('Select the logic operator for this prerequisite group.')
                ->setFormula1('"'.implode(',', $logicOptions).'"');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to add group logic validation: '.$e->getMessage());
        }
    }

    /**
     * Add dropdown validation for boolean columns (TRUE/FALSE)
     */
    private function addBooleanDropdownValidation($worksheet, string $column, int $startRow, int $endRow): void
    {
        $validation = $worksheet->getDataValidation("{$column}{$startRow}:{$column}{$endRow}");
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Boolean Value');
        $validation->setPrompt('Select TRUE or FALSE');
        $validation->setErrorTitle('Invalid Value');
        $validation->setError('Please select either TRUE or FALSE');
        $validation->setFormula1('"TRUE,FALSE"');
    }

    /**
     * Add dropdown validation for assessment type column
     */
    private function addAssessmentTypeDropdownValidation($worksheet, string $column, int $startRow, int $endRow): void
    {
        $validation = $worksheet->getDataValidation("{$column}{$startRow}:{$column}{$endRow}");
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Assessment Type');
        $validation->setPrompt('Select the type of assessment component');
        $validation->setErrorTitle('Invalid Assessment Type');
        $validation->setError('Please select a valid assessment type from the dropdown');
        $validation->setFormula1('"quiz,assignment,project,exam,online_activity,other"');
    }

    /**
     * Add instructions sheet to help users understand the template
     */
    private function addInstructionsSheet($spreadsheet, string $format): void
    {
        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Instructions');

        // Title
        $instructionsSheet->setCellValue('A1', 'Unit Import Template Instructions');
        $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $instructionsSheet->getStyle('A1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $instructionsSheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 3;

        // General instructions
        $instructionsSheet->setCellValue("A{$row}", 'General Instructions:');
        $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $instructions = [
            '• Fields marked with * are required',
            '• Unit codes must be unique',
            '• Credit points should be numeric (e.g., 12.5)',
            '• Do not modify the header row',
            '• Save the file in Excel format (.xlsx)',
        ];

        if ($format === 'simple') {
            $instructions[] = '• This template is for basic unit information only';
            $instructions[] = '• Use "Detailed" template for prerequisites';
            $instructions[] = '• Use "Complete" template for prerequisites and equivalents';
            $instructions[] = '• Use "Combined" template for units with syllabus data';
        } elseif ($format === 'combined') {
            $instructions[] = '• This template includes units, prerequisites, equivalents, syllabus, and assessments';
            $instructions[] = '• All sheets are linked by Unit Code - ensure consistency';
            $instructions[] = '• Assessment weights must sum to 100% per syllabus';
            $instructions[] = '• Assessment detail weights must sum to 100% per component';
        }

        foreach ($instructions as $instruction) {
            $instructionsSheet->setCellValue("A{$row}", $instruction);
            $row++;
        }

        $row++;

        if ($format === 'simple') {
            // Simple format specific instructions
            $instructionsSheet->setCellValue("A{$row}", 'Units Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $simpleInstructions = [
                '• Code: Unique identifier for the unit (e.g., CS101)',
                '• Name: Full name of the unit',
                '• Credit Points: Numeric value greater than 0',
                '• Remove the sample data rows before importing',
            ];

            foreach ($simpleInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }
        }

        if ($format === 'detailed' || $format === 'complete') {
            // Prerequisites sheet instructions
            $instructionsSheet->setCellValue("A{$row}", 'Prerequisites Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $prereqInstructions = [
                '• Unit Code: The unit that has prerequisites',
                '• Group Logic: Use AND when ALL conditions must be met, OR when ANY condition can be met',
                '• Group Description: Optional description for this group of prerequisites',
                '• Condition Type: Type of prerequisite condition:',
                '  - prerequisite: Must be completed before enrollment',
                '  - credit_requirement: Minimum credit points required',
                '  - co_requisite: Must be taken at the same time',
                '  - anti_requisite: Cannot be taken if this unit is completed',
                '  - textual: Free-form text requirement',
                '• Required Unit Code: Unit code for unit prerequisites (leave blank for credit/textual)',
                '• Required Credits: Number for credit_requirement type (leave blank for others)',
                '• Free Text: Additional description or text for complex requirements',
                '',
                'Complex Prerequisite Examples:',
                '• Simple: CS201 requires CS101',
                '  Row: CS201 | AND | Basic programming | prerequisite | CS101 | | |',
                '',
                '• Credit requirement: "(P)175cps And ((E) BUS30010 OR BUS30024)"',
                '  Row 1: CS301 | AND | Credit and unit requirements | credit_requirement | | 175 | 175 credit points',
                '  Row 2: CS301 | AND | Credit and unit requirements | prerequisite | CS201 | | |',
                '  Row 3: CS301 | OR | Business ethics requirement | prerequisite | BUS30010 | | |',
                '  Row 4: CS301 | OR | Business ethics requirement | prerequisite | BUS30024 | | |',
                '',
                '• Multiple groups with different logic create complex expressions',
                '• Same Unit Code + Group Description = same prerequisite group',
                '• Different Group Descriptions = separate prerequisite groups',
            ];

            foreach ($prereqInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;
        }

        if ($format === 'complete') {
            // Equivalents instructions
            $instructionsSheet->setCellValue("A{$row}", 'Equivalents Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $equivInstructions = [
                '• Unit Code: The primary unit',
                '• Equivalent Unit Code: The unit that is equivalent',
                '• Reason: Why these units are equivalent',
                '• Valid From Semester: When this equivalence takes effect (e.g., 2024-S1)',
            ];

            foreach ($equivInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }
        }

        if ($format === 'combined') {
            // Combined format specific instructions
            $row++;
            $instructionsSheet->setCellValue("A{$row}", 'Sheet-by-Sheet Instructions:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            $row++;

            // Units Sheet
            $instructionsSheet->setCellValue("A{$row}", '1. Units Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $unitsInstructions = [
                '• Code: Unique identifier (e.g., CS101)',
                '• Name: Full unit name',
                '• Credit Points: Numeric value (e.g., 12.5)',
            ];

            foreach ($unitsInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Prerequisites Sheet
            $instructionsSheet->setCellValue("A{$row}", '2. Prerequisites Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $prereqInstructions = [
                '• Unit Code: Must match a unit from Units sheet',
                '• Required Unit Code: Must match a unit from Units sheet',
                '• Type: Select from dropdown (prerequisite, co_requisite, anti_requisite)',
                '• Group Logic: Select AND/OR for combining multiple prerequisites',
                '• Description: Optional explanation of the requirement',
            ];

            foreach ($prereqInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Equivalents Sheet
            $instructionsSheet->setCellValue("A{$row}", '3. Equivalents Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $equivInstructions = [
                '• Unit Code: Must match a unit from Units sheet',
                '• Equivalent Unit Code: Can be external unit code',
                '• Reason: Explanation for equivalence',
                '• Valid From Semester: Format as YYYY-SX (e.g., 2024-S1)',
            ];

            foreach ($equivInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Syllabus Sheet
            $instructionsSheet->setCellValue("A{$row}", '4. Syllabus Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $syllabusInstructions = [
                '• Unit Code: Must match a unit from Units sheet',
                '• Version: Version identifier (e.g., v1.0, v2.1)',
                '• Description: Course description and objectives',
                '• Total Hours: Total contact hours for the unit',
                '• Hours Per Session: Hours per teaching session',
                '• Effective From Semester: Format as YYYY-SX',
                '• Is Active: Select TRUE or FALSE from dropdown',
            ];

            foreach ($syllabusInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Assessment Components Sheet
            $instructionsSheet->setCellValue("A{$row}", '5. Assessment Components Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $assessmentInstructions = [
                '• Unit Code: Must match a unit from Units sheet',
                '• Syllabus Version: Must match version from Syllabus sheet',
                '• Component Name: Name of assessment (e.g., Final Exam)',
                '• Weight: Percentage (0-100), all components must sum to 100%',
                '• Type: Select from dropdown (quiz, assignment, project, exam, etc.)',
                '• Required for Final Exam: Select TRUE or FALSE',
            ];

            foreach ($assessmentInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Assessment Details Sheet
            $instructionsSheet->setCellValue("A{$row}", '6. Assessment Details Sheet:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            $detailsInstructions = [
                '• Unit Code: Must match a unit from Units sheet',
                '• Syllabus Version: Must match version from Syllabus sheet',
                '• Component Name: Must match component from Assessment Components sheet',
                '• Detail Name: Sub-task name (e.g., Proposal, Implementation)',
                '• Weight: Percentage, all details per component must sum to 100%',
                '• Leave this sheet empty if no sub-tasks are needed',
            ];

            foreach ($detailsInstructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction);
                $row++;
            }

            $row++;

            // Important Notes
            $instructionsSheet->setCellValue("A{$row}", 'Important Notes:');
            $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;

            $importantNotes = [
                '⚠️  Unit Code consistency across all sheets is critical',
                '⚠️  Assessment weights must sum to exactly 100% per syllabus',
                '⚠️  Assessment detail weights must sum to exactly 100% per component',
                '⚠️  Use dropdown selections where available',
                '⚠️  Remove sample data before importing',
                '⚠️  Test with a small dataset first',
            ];

            foreach ($importantNotes as $note) {
                $instructionsSheet->setCellValue("A{$row}", $note);
                $row++;
            }
        }

        // Auto-size column A
        $instructionsSheet->getColumnDimension('A')->setAutoSize(true);

        // Set the Units sheet as the active sheet (first sheet)
        $spreadsheet->setActiveSheetIndex(0);
    }

    private function addUnitCodeDropdownValidation($worksheet, string $column, int $startRow, int $endRow): void
    {
        // Get existing unit codes for validation
        $units = \App\Models\Unit::orderBy('code')->pluck('code')->toArray();

        if (empty($units)) {
            // Add some common examples if no units exist
            $units = ['CS101', 'CS201', 'BUS30010', 'BUS30024', 'MATH101'];
        }

        // Limit the number of items to prevent Excel corruption
        $units = array_slice($units, 0, 100);

        // Ensure the formula string doesn't exceed Excel's limits
        $formula = '"'.implode(',', $units).'"';
        if (strlen($formula) > 255) {
            // Fallback to fewer items if formula is too long
            $units = array_slice($units, 0, 20);
            $formula = '"'.implode(',', $units).'"';
        }

        try {
            $validation = $worksheet->getDataValidation($column.$startRow.':'.$column.$endRow);
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)
                ->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('Invalid Unit Code')
                ->setError('Please enter a valid unit code.')
                ->setPromptTitle('Unit Code')
                ->setPrompt('Enter or select a unit code.')
                ->setFormula1($formula);
        } catch (\Exception $e) {
            // If validation fails, skip it to prevent corruption
            \Illuminate\Support\Facades\Log::warning('Failed to add unit code validation: '.$e->getMessage());
        }
    }
}
