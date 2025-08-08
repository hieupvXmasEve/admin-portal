<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;
use App\Services\UserExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserImportController extends Controller
{
    public function __construct(
        private readonly UserExcelImportService $importService
    ) {}

    public function showImportForm(): InertiaResponse
    {
        return Inertia::render('users/Import', [
            'maxFileSize' => config('import.max_file_size', '10MB'),
            'allowedExtensions' => config('import.allowed_extensions', ['xlsx', 'xls']),
            'availableFormats' => [
                'simple' => 'Simple Format (Single Sheet)',
                'detailed' => 'Detailed Format (Multiple Sheets)',
                'relationship' => 'Relationship Format (One row per relationship)',
            ],
        ]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        Log::info('Upload request received', [
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

            Log::info('Attempting file upload', [
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

                Log::info('File move completed', [
                    'full_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                ]);
            } catch (\Exception $moveException) {
                Log::error('File move failed', [
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

            Log::info('File uploaded successfully', [
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
            Log::error('File upload failed: '.$e->getMessage(), [
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
            Log::error('Preview failed: '.$e->getMessage(), [
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
            'create_missing_campuses' => 'nullable|boolean',
        ]);

        $startTime = microtime(true);

        try {
            $fullPath = storage_path('app/'.$request->file_path);

            Log::info('Processing import', [
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
                'create_missing_campuses' => $request->create_missing_campuses ?? false,
            ];

            $result = $this->importService->importUsersFromExcel($fullPath, $options);

            // Calculate processing time
            $processingTime = round(microtime(true) - $startTime, 2);
            $result['summary']['processing_time'] = $processingTime.' seconds';

            // Clean up temporary file
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Temporary file cleaned up', ['path' => $fullPath]);
            }

            // Log successful import
            Log::info('Import completed successfully', [
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
                Log::info('Temporary file cleaned up after error', ['path' => $fullPath]);
            }

            Log::error('Import failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file_path' => $request->file_path,
                'options' => $request->only(['duplicate_handling', 'create_missing_campuses']),
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
            'simple' => 'users_simple_template.xlsx',
            'detailed' => 'users_detailed_template.xlsx',
            'relationship' => 'users_relationship_template.xlsx',
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
            'permissions' => session('permissions', []),
            'has_import_permission' => session('permissions', collect())->contains('import_user'),
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

        $templatePath = $templateDir."/users_{$format}_template.xlsx";

        // Create a simple Excel file with headers based on format
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;

        switch ($format) {
            case 'simple':
                $this->createSimpleTemplate($spreadsheet);
                break;
            case 'detailed':
                $this->createDetailedTemplate($spreadsheet);
                break;
            case 'relationship':
                $this->createRelationshipTemplate($spreadsheet);
                break;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($templatePath);

        return $templatePath;
    }

    private function createSimpleTemplate($spreadsheet): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Users');

        $headers = ['Name*', 'Email*', 'Password', 'Campus Codes', 'Role Codes', 'Phone', 'Address'];
        $worksheet->fromArray($headers, null, 'A1');

        // Add sample data
        $sampleData = [
            'John Doe',
            'john@example.com',
            'password123',
            'HN,HCM',
            'super_admin,giam_doc_dao_tao',
            '+1234567890',
            '123 Main St',
        ];
        $worksheet->fromArray($sampleData, null, 'A2');

        // Style headers
        $worksheet->getStyle('A1:G1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $worksheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        // Auto-size columns
        foreach (range('A', 'G') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function createDetailedTemplate($spreadsheet): void
    {
        // Users sheet
        $usersSheet = $spreadsheet->getActiveSheet();
        $usersSheet->setTitle('Users');

        $userHeaders = ['Name*', 'Email*', 'Password', 'Phone', 'Address', 'Email Verified'];
        $usersSheet->fromArray($userHeaders, null, 'A1');

        // Sample user data
        $usersSheet->fromArray(['John Doe', 'john@example.com', 'password123', '+1234567890', '123 Main St', 'Yes'], null, 'A2');

        // User Campus Roles sheet
        $rolesSheet = $spreadsheet->createSheet();
        $rolesSheet->setTitle('User Campus Roles');

        $roleHeaders = ['User Email*', 'Campus Code*', 'Role Code*', 'Assigned Date'];
        $rolesSheet->fromArray($roleHeaders, null, 'A1');

        // Sample relationship data
        $rolesSheet->fromArray(['john@example.com', 'HN', 'super_admin', '2025-01-01'], null, 'A2');

        // Style both sheets
        foreach ([$usersSheet, $rolesSheet] as $sheet) {
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
    }

    private function createRelationshipTemplate($spreadsheet): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('User Relationships');

        $headers = ['User Name*', 'User Email*', 'Campus Code*', 'Campus Name', 'Role Code*', 'Password', 'Phone'];
        $worksheet->fromArray($headers, null, 'A1');

        // Add sample data
        $sampleData = [
            ['John Doe', 'john@example.com', 'HN', 'Swinburne Hà Nội', 'super_admin', 'password123', '+1234567890'],
            ['John Doe', 'john@example.com', 'HCM', 'Swinburne Hồ Chí Minh', 'giam_doc_dao_tao', 'password123', '+1234567890'],
        ];

        foreach ($sampleData as $index => $row) {
            $worksheet->fromArray($row, null, 'A'.($index + 2));
        }

        // Style headers
        $worksheet->getStyle('A1:G1')->getFont()->setBold(true);
        $worksheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4472C4');
        $worksheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        // Auto-size columns
        foreach (range('A', 'G') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
