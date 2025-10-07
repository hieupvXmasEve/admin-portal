<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ScholarshipDefinition;
use App\Models\StudentScholarshipAward;
use App\Models\StudentCashWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class StudentFinancialImportService
{
    /**
     * Batch size for processing large imports
     */
    protected int $batchSize = 100;

    /**
     * Maximum allowed amount per transaction
     */
    protected float $maxAmount = 1000000000; // 1 billion VND

    /**
     * Column mapping for automatic detection
     */
    protected array $columnMappings = [
        'student_id' => ['student id', 'student_id', 'id', 'student code', 'student_code'],
        'scholarship_code' => ['scholarship code', 'scholarship_code', 'scholarship', 'code'],
        'voucher_codes' => ['voucher codes', 'voucher_codes', 'vouchers', 'voucher'],
        'paid_amount' => ['paid amount', 'paid_amount', 'payment', 'amount', 'payment amount'],
        'payment_date' => ['payment date', 'payment_date', 'date', 'paid date', 'paid_date'],
        'notes' => ['notes', 'note', 'remarks', 'comment', 'comments'],
    ];

    /**
     * Preview import data from uploaded file
     */
    public function previewImport(UploadedFile $file, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Validate file
            $this->validateFile($file);

            // Store file temporarily
            $filePath = $this->storeTemporaryFile($file);

            // Analyze file structure
            $analysis = $this->analyzeFile($filePath);

            // Detect column mapping
            $columnMapping = $this->detectColumnMapping($analysis['headers']);

            // Get preview data
            $previewRows = $options['preview_rows'] ?? 10;
            $previewData = $this->getPreviewData($filePath, $columnMapping, $previewRows);

            // Validate preview data
            $validationSummary = $this->validatePreviewData($previewData);

            $executionTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'data' => [
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'temp_path' => $filePath,
                    ],
                    'detected_columns' => $analysis['headers'],
                    'column_mapping' => $columnMapping,
                    'unmapped_columns' => array_diff($analysis['headers'], array_keys($columnMapping)),
                    'preview_data' => $previewData,
                    'total_rows' => $analysis['total_rows'],
                    'validation_summary' => $validationSummary,
                    'execution_time' => $executionTime . 's',
                ]
            ];
        } catch (Throwable $e) {
            Log::error('Student financial import preview failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process the import
     */
    public function processImport(UploadedFile $file, array $columnMapping, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Validate file
            $this->validateFile($file);

            // Store file temporarily
            $filePath = $this->storeTemporaryFile($file);

            // Process import with transaction
            $results = DB::transaction(function () use ($filePath, $columnMapping, $options) {
                return $this->importStudentData($filePath, $columnMapping, $options);
            });

            // Clean up temporary file
            $this->cleanupTemporaryFile($filePath);

            $executionTime = round(microtime(true) - $startTime, 2);
            $results['execution_time'] = $executionTime . 's';

            Log::info('Student financial import completed', [
                'results' => $results,
                'execution_time' => $executionTime
            ]);

            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Throwable $e) {
            Log::error('Student financial import processing failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Import student financial data with batch processing
     */
    protected function importStudentData(string $filePath, array $columnMapping, array $options = []): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);

        // Get headers
        $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
        $headers = [];
        foreach ($headerRow as $index => $header) {
            if (!empty(trim($header))) {
                $headers[trim($header)] = $index + 1;
            }
        }

        $results = [
            'total_rows' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'scholarships_assigned' => 0,
            'payments_processed' => 0,
            'errors' => [],
            'warnings' => [],
            'batches_processed' => 0,
        ];

        $startRow = 2;
        $endRow = $worksheet->getHighestRow();
        $totalRows = $endRow - $startRow + 1;

        // Process in batches to prevent timeout and memory issues
        for ($batchStart = $startRow; $batchStart <= $endRow; $batchStart += $this->batchSize) {
            $batchEnd = min($batchStart + $this->batchSize - 1, $endRow);
            
            try {
                // Process each batch in its own transaction
                DB::transaction(function () use ($worksheet, $headers, $columnMapping, $batchStart, $batchEnd, &$results) {
                    for ($row = $batchStart; $row <= $batchEnd; $row++) {
                        $results['total_rows']++;

                        try {
                            $rowData = $this->extractRowData($worksheet, $row, $headers, $columnMapping);

                            // Validate student ID
                            if (empty($rowData['student_id'])) {
                                $results['skipped']++;
                                $results['errors'][] = "Row {$row}: Student ID is required";
                                continue;
                            }

                            // Find student by student_id column
                            $student = Student::where('student_id', $rowData['student_id'])->first();
                            if (!$student) {
                                $results['failed']++;
                                $results['errors'][] = "Row {$row}: Student ID {$rowData['student_id']} not found";
                                continue;
                            }

                            // Check if student already has a scholarship award
                            // If yes, SKIP entire row (both scholarship and payment) to prevent duplicate import
                            $existingAward = StudentScholarshipAward::where('student_id', $student->id)->exists();
                            if ($existingAward) {
                                $results['skipped']++;
                                $results['warnings'][] = "Row {$row}: Student {$rowData['student_id']} already has scholarship award - skipped to prevent duplicate";
                                continue;
                            }

                            // Process scholarship assignment
                            if (!empty($rowData['scholarship_code'])) {
                                $scholarshipResult = $this->processScholarshipAssignment(
                                    $student,
                                    $rowData['scholarship_code'],
                                    $row
                                );

                                if ($scholarshipResult['success']) {
                                    $results['scholarships_assigned']++;
                                } else {
                                    $results['warnings'][] = $scholarshipResult['message'];
                                }
                            }

                            // Process payment
                            if (!empty($rowData['paid_amount']) && $rowData['paid_amount'] > 0) {
                                $paymentResult = $this->processPayment(
                                    $student,
                                    $rowData['paid_amount'],
                                    $rowData['payment_date'] ?? now(),
                                    $rowData['notes'] ?? null,
                                    $row
                                );

                                if ($paymentResult['success']) {
                                    $results['payments_processed']++;
                                } else {
                                    // If payment fails, it's a critical error - throw exception to rollback batch
                                    throw new \Exception($paymentResult['message']);
                                }
                            }

                            $results['successful']++;
                        } catch (Throwable $e) {
                            // Throw exception to rollback entire batch
                            throw new \Exception("Row {$row}: " . $e->getMessage(), 0, $e);
                        }
                    }
                });

                $results['batches_processed']++;
                
                // Log progress for large imports
                if ($totalRows > $this->batchSize) {
                    $progress = round(($results['total_rows'] / $totalRows) * 100, 1);
                    Log::info("Import progress: {$progress}% ({$results['total_rows']}/{$totalRows} rows)", [
                        'batch' => $results['batches_processed'],
                        'successful' => $results['successful'],
                        'failed' => $results['failed'],
                    ]);
                }
                
            } catch (Throwable $e) {
                // Batch failed - log and stop import to prevent partial data
                $results['failed'] += ($batchEnd - $batchStart + 1);
                $results['errors'][] = "Batch {$results['batches_processed']} (rows {$batchStart}-{$batchEnd}): " . $e->getMessage();
                
                Log::error('Batch processing failed in student financial import', [
                    'batch_start' => $batchStart,
                    'batch_end' => $batchEnd,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Stop processing on batch failure to maintain data consistency
                throw new \Exception(
                    "Import stopped at batch {$results['batches_processed']} due to errors. " .
                    "Rows {$batchStart}-{$batchEnd} were rolled back. " .
                    "Error: " . $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Process scholarship assignment for a student
     */
    protected function processScholarshipAssignment(Student $student, string $scholarshipCode, int $row): array
    {
        try {
            // Validate scholarship code exists
            $scholarship = ScholarshipDefinition::where('code', $scholarshipCode)->first();
            if (!$scholarship) {
                return [
                    'success' => false,
                    'message' => "Row {$row}: Scholarship code '{$scholarshipCode}' not found"
                ];
            }

            // Check if student already has a scholarship
            $existingAward = StudentScholarshipAward::where('student_id', $student->id)->first();

            if ($existingAward) {
                // Update existing scholarship
                $existingAward->update([
                    'scholarship_code' => $scholarshipCode,
                    'awarded_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => "Row {$row}: Updated scholarship for student {$student->id}"
                ];
            } else {
                // Create new scholarship assignment
                StudentScholarshipAward::create([
                    'student_id' => $student->id,
                    'scholarship_code' => $scholarshipCode,
                    'awarded_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => "Row {$row}: Assigned scholarship to student {$student->id}"
                ];
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Row {$row}: Failed to assign scholarship - " . $e->getMessage()
            ];
        }
    }

    /**
     * Validate payment amount
     */
    protected function validateAmount(float $amount, int $row): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Row {$row}: Payment amount must be positive (got: {$amount})");
        }

        if ($amount > $this->maxAmount) {
            throw new \InvalidArgumentException(
                "Row {$row}: Payment amount exceeds maximum limit of " . number_format($this->maxAmount, 0, '.', ',') . " VND"
            );
        }

        // Check for excessive decimal places (max 2 decimal places for VND)
        if (round($amount, 2) != $amount) {
            throw new \InvalidArgumentException(
                "Row {$row}: Payment amount must have maximum 2 decimal places (got: {$amount})"
            );
        }

        // Check for suspiciously small amounts (less than 1000 VND)
        if ($amount < 1000) {
            throw new \InvalidArgumentException(
                "Row {$row}: Payment amount too small (minimum: 1,000 VND, got: {$amount})"
            );
        }
    }

    /**
     * Process payment for a student with proper locking and validation
     */
    protected function processPayment(Student $student, float $amount, $paymentDate, ?string $notes, int $row): array
    {
        try {
            // Validate amount first
            $this->validateAmount($amount, $row);

            // Use transaction with wallet locking to prevent race conditions
            DB::transaction(function () use ($student, $amount, $paymentDate, $notes, $row) {
                // Lock the wallet for update to prevent concurrent modifications
                $wallet = StudentCashWallet::lockForUpdate()
                    ->firstOrCreate(
                        ['student_id' => $student->id],
                        ['balance' => 0, 'currency' => 'VND']
                    );

                // If wallet was just created, we need to lock it again
                if ($wallet->wasRecentlyCreated) {
                    $wallet = StudentCashWallet::lockForUpdate()
                        ->where('student_id', $student->id)
                        ->first();
                }

                // Record the deposit transaction
                $balanceBefore = $wallet->balance;
                $balanceAfter = $balanceBefore + $amount;

                // Create transaction record
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'transaction_type' => 'deposit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => $notes ?? 'Payment imported from Excel',
                    'created_by' => auth()->id(),
                ]);

                // Update wallet balance atomically
                $wallet->update(['balance' => $balanceAfter]);
            });

            return [
                'success' => true,
                'message' => "Row {$row}: Processed payment of " . number_format($amount, 0, '.', ',') . " VND for student {$student->student_id}"
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Row {$row}: Failed to process payment - " . $e->getMessage()
            ];
        }
    }

    /**
     * Extract row data based on column mapping
     */
    protected function extractRowData($worksheet, int $row, array $headers, array $columnMapping): array
    {
        $rowData = [];

        foreach ($columnMapping as $excelColumn => $dbField) {
            $columnIndex = $headers[$excelColumn] ?? null;
            if ($columnIndex === null) {
                $rowData[$dbField] = null;
                continue;
            }

            $columnLetter = $this->getColumnLetter($columnIndex);
            $cellValue = $worksheet->getCell($columnLetter . $row)->getCalculatedValue();

            // Convert Excel date if needed
            if ($dbField === 'payment_date' && is_numeric($cellValue)) {
                $cellValue = Date::excelToDateTimeObject($cellValue)->format('Y-m-d');
            }

            // Convert amount to float
            if ($dbField === 'paid_amount' && !empty($cellValue)) {
                $cellValue = (float) $cellValue;
            }

            $rowData[$dbField] = $cellValue;
        }

        return $rowData;
    }

    /**
     * Generate Excel template for student financial data
     */
    public function generateTemplate(): string
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'Student ID',
                'Scholarship Code',
                'Voucher Codes',
                'Paid Amount',
                'Payment Date',
                'Notes'
            ];

            $sheet->fromArray($headers, null, 'A1');

            // Add sample data
            $sampleData = [
                [1, 'MERIT2024', '', 5000000, '2024-01-15', 'Initial payment'],
                [2, 'SPORTS2024', 'VOUCHER001,VOUCHER002', 3000000, '2024-01-20', 'Partial payment'],
                [3, '', '', 10000000, '2024-01-25', 'Full payment'],
            ];

            $sheet->fromArray($sampleData, null, 'A2');

            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'student_financial_template_' . date('Y-m-d') . '.xlsx';
            $relativePath = 'temp/templates/' . $filename;

            // Ensure directory exists
            Storage::makeDirectory('temp/templates');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fullPath = Storage::path($relativePath);
            $writer->save($fullPath);

            if (!file_exists($fullPath)) {
                throw new \Exception('Template file was not created at: ' . $fullPath);
            }

            return $fullPath;
        } catch (Throwable $e) {
            Log::error('Failed to generate student financial template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to generate template: ' . $e->getMessage());
        }
    }

    /**
     * Validate uploaded file
     */
    protected function validateFile(UploadedFile $file): void
    {
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv'
        ];
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('File must be an Excel file (.xlsx, .xls) or CSV (.csv)');
        }

        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
            throw new \InvalidArgumentException('File extension must be .xlsx, .xls, or .csv');
        }

        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException('File size must not exceed 10MB');
        }
    }

    /**
     * Store file temporarily
     */
    protected function storeTemporaryFile(UploadedFile $file): string
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = 'temp/imports/' . $filename;

        Storage::makeDirectory('temp/imports');
        Storage::putFileAs('temp/imports', $file, $filename);

        return Storage::path($path);
    }

    /**
     * Analyze Excel file structure
     */
    protected function analyzeFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);

        $headers = [];
        $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
        foreach ($headerRow as $header) {
            if (!empty(trim($header))) {
                $headers[] = trim($header);
            }
        }

        $totalRows = $worksheet->getHighestRow() - 1;

        return [
            'headers' => $headers,
            'total_rows' => max(0, $totalRows),
            'sheet_name' => $worksheet->getTitle(),
            'total_sheets' => $spreadsheet->getSheetCount(),
        ];
    }

    /**
     * Detect column mapping automatically
     */
    protected function detectColumnMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($this->columnMappings as $dbField => $possibleHeaders) {
                foreach ($possibleHeaders as $possibleHeader) {
                    if ($normalizedHeader === strtolower($possibleHeader)) {
                        $mapping[$header] = $dbField;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Get preview data from file
     */
    protected function getPreviewData(string $filePath, array $columnMapping, int $previewRows = 10): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);

        $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
        $headers = [];
        foreach ($headerRow as $index => $header) {
            if (!empty(trim($header))) {
                $headers[trim($header)] = $index + 1;
            }
        }

        $data = [];
        $startRow = 2;
        $endRow = min($startRow + $previewRows - 1, $worksheet->getHighestRow());

        for ($row = $startRow; $row <= $endRow; $row++) {
            $rowData = [];

            foreach ($columnMapping as $excelColumn => $dbField) {
                $columnIndex = $headers[$excelColumn] ?? null;
                if ($columnIndex === null) {
                    $rowData[$dbField] = null;
                    continue;
                }

                $columnLetter = $this->getColumnLetter($columnIndex);
                $cellValue = $worksheet->getCell($columnLetter . $row)->getCalculatedValue();

                if ($dbField === 'payment_date' && is_numeric($cellValue)) {
                    $cellValue = Date::excelToDateTimeObject($cellValue)->format('Y-m-d');
                }

                if ($dbField === 'paid_amount' && !empty($cellValue)) {
                    $cellValue = (float) $cellValue;
                }

                $rowData[$dbField] = $cellValue;
            }

            $data[] = [
                'row_number' => $row,
                'data' => $rowData,
                'errors' => [],
                'warnings' => [],
                'is_valid' => true,
            ];
        }

        return $data;
    }

    /**
     * Validate preview data
     */
    protected function validatePreviewData(array $previewData): array
    {
        $validRows = 0;
        $invalidRows = 0;
        $warnings = [];

        foreach ($previewData as &$row) {
            $errors = [];
            $rowWarnings = [];

            // Check required field
            if (empty($row['data']['student_id'])) {
                $errors[] = 'Student ID is required';
            } else {
                // Check if student exists by student_id column
                $student = Student::where('student_id', $row['data']['student_id'])->first();
                if (!$student) {
                    $errors[] = 'Student ID not found in system';
                } else {
                    $rowWarnings[] = 'Student found: ' . $student->full_name;
                }
            }

            // Validate scholarship code if provided
            if (!empty($row['data']['scholarship_code'])) {
                $scholarship = ScholarshipDefinition::where('code', $row['data']['scholarship_code'])->first();
                if (!$scholarship) {
                    $errors[] = 'Scholarship code not found';
                } else {
                    $rowWarnings[] = 'Scholarship: ' . $scholarship->name;
                }
            }

            // Validate payment amount
            if (!empty($row['data']['paid_amount'])) {
                if ($row['data']['paid_amount'] < 0) {
                    $errors[] = 'Payment amount must be positive';
                } else {
                    $rowWarnings[] = 'Payment will be added to wallet';
                }
            }

            $row['errors'] = $errors;
            $row['warnings'] = $rowWarnings;
            $row['is_valid'] = empty($errors);

            if (empty($errors)) {
                $validRows++;
            } else {
                $invalidRows++;
            }

            $warnings = array_merge($warnings, $rowWarnings);
        }

        return [
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'warnings' => array_unique($warnings),
        ];
    }

    /**
     * Clean up temporary file
     */
    protected function cleanupTemporaryFile(string $filePath): void
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to cleanup temporary import file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Convert column index to Excel column letter
     */
    protected function getColumnLetter(int $columnIndex): string
    {
        $columnLetter = '';
        $columnIndex--;

        while ($columnIndex >= 0) {
            $columnLetter = chr(65 + ($columnIndex % 26)) . $columnLetter;
            $columnIndex = intval($columnIndex / 26) - 1;
        }

        return $columnLetter;
    }
}
