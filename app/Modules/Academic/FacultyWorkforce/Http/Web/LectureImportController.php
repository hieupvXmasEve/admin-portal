<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\PreviewLectureImportRequest;
use App\Http\Requests\Lecture\ProcessLectureImportRequest;
use App\Http\Requests\Lecture\UploadLectureImportFileRequest;
use App\Http\Responses\ApiResponse;
use App\Services\LectureExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LectureImportController extends Controller
{
    public function __construct(
        private readonly LectureExcelImportService $importService
    ) {}

    public function showImportForm(): InertiaResponse
    {
        return Inertia::render('Lectures/Import', [
            'maxFileSize' => config('import.max_file_size', '10MB'),
            'allowedExtensions' => config('import.allowed_extensions', ['xlsx', 'xls']),
            'availableFormats' => [
                'simple' => 'Simple Format (Single Sheet)',
            ],
        ]);
    }

    public function uploadFile(UploadLectureImportFileRequest $request): JsonResponse
    {
        Log::info('Lecture import upload request received', [
            'has_file' => $request->hasFile('file'),
            'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : 'N/A',
            'file_name' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : 'N/A',
            'content_length' => $request->header('Content-Length'),
            'user_id' => Auth::id(),
        ]);

        $request->validated();

        try {
            $file = $request->file('file');
            $filename = time().'_'.$file->getClientOriginalName();

            // Ensure the directory exists
            $uploadDir = storage_path('app/temp/imports');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            Log::info('Attempting lecture import file upload', [
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

                Log::info('Lecture import file move completed', [
                    'full_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                ]);
            } catch (\Exception $moveException) {
                Log::error('Failed to move lecture import file', [
                    'error' => $moveException->getMessage(),
                    'upload_dir' => $uploadDir,
                ]);

                return ApiResponse::compatible([
                    'success' => false,
                    'error' => 'Failed to save uploaded file',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Validate file by attempting to read headers
            try {
                $preview = $this->importService->previewImportData($fullPath, 5);

                Log::info('Lecture import file validated successfully', [
                    'headers_count' => count($preview['headers']),
                    'total_rows' => $preview['total_rows'],
                    'estimated_lecturers' => $preview['estimated_lecturers'],
                ]);

                return ApiResponse::compatible([
                    'success' => true,
                    'file_path' => $path,
                    'preview' => $preview,
                    'message' => 'File uploaded and validated successfully',
                ]);
            } catch (\Exception $validationException) {
                // Clean up the uploaded file if validation fails
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                Log::warning('Lecture import file validation failed', [
                    'error' => $validationException->getMessage(),
                    'file_cleaned_up' => true,
                ]);

                return ApiResponse::compatible([
                    'success' => false,
                    'error' => 'File validation failed: '.$validationException->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            Log::error('Lecture import upload failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::compatible([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function previewImport(PreviewLectureImportRequest $request): JsonResponse
    {
        $request->validated();

        try {
            $fullPath = storage_path('app/'.$request->file_path);
            $previewRows = $request->preview_rows ?? 10;

            $preview = $this->importService->previewImportData($fullPath, $previewRows);

            return ApiResponse::compatible([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            Log::error('Lecture import preview failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file_path' => $request->file_path,
            ]);

            return ApiResponse::compatible([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function processImport(ProcessLectureImportRequest $request): JsonResponse
    {
        $request->validated();

        $startTime = microtime(true);

        try {
            $fullPath = storage_path('app/'.$request->file_path);

            Log::info('Processing lecture import', [
                'file_path' => $request->file_path,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'user_id' => Auth::id(),
            ]);

            // Check if file exists
            if (! file_exists($fullPath)) {
                throw new \Exception('Import file not found at: '.$fullPath);
            }

            $options = [
                'duplicate_handling' => $request->duplicate_handling ?? 'update',
            ];

            $result = $this->importService->importLecturersFromExcel($fullPath, $options);

            // Calculate processing time
            $processingTime = round(microtime(true) - $startTime, 2);
            $result['summary']['processing_time'] = $processingTime.' seconds';

            // Clean up temporary file
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Temporary lecture import file cleaned up', ['path' => $fullPath]);
            }

            // Log successful import
            Log::info('Lecture import completed successfully', [
                'user_id' => Auth::id(),
                'summary' => $result['summary'],
            ]);

            return ApiResponse::compatible([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            // Calculate processing time even for failures
            $processingTime = round(microtime(true) - $startTime, 2);

            Log::error('Lecture import failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file_path' => $request->file_path,
                'processing_time' => $processingTime.' seconds',
            ]);

            // Try to clean up the file
            $fullPath = storage_path('app/'.$request->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Temporary lecture import file cleaned up after error', ['path' => $fullPath]);
            }

            return ApiResponse::compatible([
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime.' seconds',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function downloadTemplate(string $format = 'simple'): BinaryFileResponse
    {
        try {
            $templates = [
                'simple' => $this->generateSimpleTemplate(),
            ];

            if (! isset($templates[$format])) {
                throw new \Exception('Invalid template format requested');
            }

            $filePath = $templates[$format];
            $fileName = "lecturer_import_template_{$format}.xlsx";

            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Template download failed: '.$e->getMessage(), [
                'format' => $format,
                'user_id' => Auth::id(),
            ]);

            abort(500, 'Template generation failed');
        }
    }

    private function generateSimpleTemplate(): string
    {
        // Create a simple template with all required and optional headers
        $headers = [
            'Employee ID *',
            'Title',
            'First Name *',
            'Last Name *',
            'Email *',
            'Phone',
            'Mobile Phone',
            'Campus Code *',
            'Department',
            'Faculty',
            'Specialization',
            'Expertise Areas',
            'Academic Rank *',
            'Highest Degree',
            'Degree Field',
            'Alma Mater',
            'Graduation Year',
            'Hire Date *',
            'Contract Start Date',
            'Contract End Date',
            'Employment Type *',
            'Employment Status *',
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
            'Biography',
            'Certifications',
            'Languages',
            'Is Active',
            'Can Teach Online',
            'Is Available For Assignment',
            'Notes',
        ];

        // Sample data row for guidance
        $sampleData = [
            'EMP001',
            'Dr.',
            'John',
            'Doe',
            'john.doe@university.edu',
            '+1-555-123-4567',
            '+1-555-987-6543',
            'MAIN',
            'Computer Science',
            'Engineering',
            'Artificial Intelligence',
            'Machine Learning, Data Science, AI',
            'Senior Lecturer',
            'PhD',
            'Computer Science',
            'MIT',
            '2010',
            '2015-09-01',
            '2023-01-01',
            '2025-12-31',
            'Full Time',
            'Active',
            'Monday, Tuesday, Wednesday',
            '09:00',
            '17:00',
            '40',
            'in_person, online, hybrid',
            'Room 301, CS Building',
            '+1-555-111-2222',
            'Jane Doe',
            '+1-555-333-4444',
            'Spouse',
            'Experienced researcher in AI and machine learning.',
            'AWS Certified, Google Cloud Professional',
            'English, French, Spanish',
            'true',
            'true',
            'true',
            'Available for additional projects.',
        ];

        // Create temporary Excel file
        $tempFile = tempnam(sys_get_temp_dir(), 'lecturer_template_');
        $tempFile .= '.xlsx';

        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Lecturer Import Template');

        // Set headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $worksheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }

        // Set sample data
        $colIndex = 1;
        foreach ($sampleData as $value) {
            $worksheet->setCellValueByColumnAndRow($colIndex, 2, $value);
            $colIndex++;
        }

        // Style the headers
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A1:'.$lastColumn.'1';
        $worksheet->getStyle($headerRange)->getFont()->setBold(true);
        $worksheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $worksheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');

        // Auto-size columns
        for ($i = 1; $i <= count($headers); $i++) {
            $columnLetter = Coordinate::stringFromColumnIndex($i);
            $worksheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
