<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Student;
use App\Models\VoucherDefinition;
use App\Models\VoucherRedemption;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VoucherImportService
{
    /**
     * Detect column indices for student_id and voucher_codes from header row.
     * Scans all columns and matches by header names (case-insensitive).
     *
     * @return array{student_id_col: int|null, voucher_codes_col: int|null}
     */
    private function detectColumns(array $headerRow): array
    {
        $studentIdCol = null;
        $voucherCodesCol = null;

        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim($header ?? ''));
            $normalized = str_replace([' ', '_', '-'], '', $normalized);

            // Match student_id column (various formats)
            if (in_array($normalized, ['studentid', 'student', 'sid', 'id'])) {
                $studentIdCol = $index;
            }

            // Match voucher_codes column (various formats)
            if (in_array($normalized, ['vouchercodes', 'vouchers', 'voucher', 'codes', 'code', 'vouchercode'])) {
                $voucherCodesCol = $index;
            }
        }

        return [
            'student_id_col' => $studentIdCol,
            'voucher_codes_col' => $voucherCodesCol,
        ];
    }

    /**
     * Parse student ID and voucher codes from row using detected column indices.
     */
    private function parseRowData(array $row, int $studentIdCol, int $voucherCodesCol): array
    {
        $studentId = isset($row[$studentIdCol]) ? trim($row[$studentIdCol]) : '';
        $voucherCodesStr = isset($row[$voucherCodesCol]) ? trim($row[$voucherCodesCol]) : '';

        $voucherCodes = array_filter(array_map('trim', explode(',', $voucherCodesStr)));

        return [
            'student_id' => $studentId,
            'voucher_codes' => $voucherCodes,
        ];
    }

    /**
     * Preview voucher import from Excel file.
     */
    public function previewImport(UploadedFile $file, int $billingCycleId): array
    {
        $billingCycle = BillingCycle::findOrFail($billingCycleId);
        $preview = [];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Validate header row
            $headerRow = array_shift($rows);
            if (empty($headerRow)) {
                throw new \Exception('Excel file is empty or missing header row.');
            }

            // Detect columns
            $columns = $this->detectColumns($headerRow);

            if ($columns['student_id_col'] === null || $columns['voucher_codes_col'] === null) {
                throw new \Exception('Required columns not found. Please ensure your Excel file has columns for Student ID (student_id, student, sid, or id) and Voucher Codes (voucher_codes, vouchers, voucher, codes, code, or voucher_code).');
            }

            foreach ($rows as $rowIndex => $row) {
                $lineNumber = $rowIndex + 2; // +2 because we skipped header and arrays are 0-indexed

                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Parse student ID and voucher codes
                $parsed = $this->parseRowData($row, $columns['student_id_col'], $columns['voucher_codes_col']);
                $studentId = $parsed['student_id'];
                $voucherCodes = $parsed['voucher_codes'];

                // Skip rows with no student ID or voucher codes (silently)
                if (empty($studentId) || empty($voucherCodes)) {
                    continue;
                }

                $previewRow = [
                    'row_number' => $lineNumber,
                    'student_id' => $studentId,
                    'voucher_codes' => $voucherCodes,
                    'is_valid' => true,
                    'errors' => [],
                    'warnings' => [],
                ];

                // Validate student exists
                if (! empty($studentId)) {
                    $student = Student::where('student_id', $studentId)->first();
                    if (! $student) {
                        $previewRow['is_valid'] = false;
                        $previewRow['errors'][] = "Student not found: {$studentId}";
                    }
                }

                // Validate each voucher
                if (! empty($voucherCodes)) {
                    foreach ($voucherCodes as $voucherCode) {
                        if (empty($voucherCode)) {
                            continue;
                        }

                        $voucher = VoucherDefinition::where('code', $voucherCode)->first();
                        if (! $voucher) {
                            $previewRow['is_valid'] = false;
                            $previewRow['errors'][] = "Voucher not found: {$voucherCode}";

                            continue;
                        }

                        if (! $voucher->is_active) {
                            $previewRow['is_valid'] = false;
                            $previewRow['errors'][] = "Voucher is not active: {$voucherCode}";
                        }

                        $now = now();
                        if ($now->lessThan($voucher->valid_from) || $now->greaterThan($voucher->valid_until)) {
                            $previewRow['is_valid'] = false;
                            $previewRow['errors'][] = "Voucher is not valid: {$voucherCode} (valid from {$voucher->valid_from->format('Y-m-d')} to {$voucher->valid_until->format('Y-m-d')})";
                        }

                        // Check for existing redemption
                        if ($student && $voucher) {
                            $existing = VoucherRedemption::where('student_id', $student->id)
                                ->where('voucher_id', $voucher->id)
                                ->where('billing_cycle_id', $billingCycleId)
                                ->exists();

                            if ($existing) {
                                $previewRow['warnings'][] = "Voucher {$voucherCode} already redeemed by this student for this billing cycle (will be skipped)";
                            }
                        }
                    }
                }

                $preview[] = $previewRow;
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to process Excel file: '.$e->getMessage());
        }

        return $preview;
    }

    /**
     * Process voucher import from Excel file.
     *
     * @return array{success: bool, stats: array, details: array}
     */
    public function processImport(UploadedFile $file, int $billingCycleId): array
    {
        $billingCycle = BillingCycle::findOrFail($billingCycleId);

        $stats = [
            'total' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $details = [];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Validate header row
            $headerRow = array_shift($rows);
            if (empty($headerRow)) {
                throw new \Exception('Excel file is empty or missing header row.');
            }

            // Detect columns
            $columns = $this->detectColumns($headerRow);

            if ($columns['student_id_col'] === null || $columns['voucher_codes_col'] === null) {
                throw new \Exception('Required columns not found. Please ensure your Excel file has columns for Student ID (student_id, student, sid, or id) and Voucher Codes (voucher_codes, vouchers, voucher, codes, code, or voucher_code).');
            }

            foreach ($rows as $rowIndex => $row) {
                $lineNumber = $rowIndex + 2;

                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Parse student ID and voucher codes
                $parsed = $this->parseRowData($row, $columns['student_id_col'], $columns['voucher_codes_col']);
                $studentId = $parsed['student_id'];
                $voucherCodes = $parsed['voucher_codes'];

                // Skip rows with no student ID or voucher codes (silently)
                if (empty($studentId) || empty($voucherCodes)) {
                    continue;
                }

                // Find student
                $student = Student::where('student_id', $studentId)->first();
                if (! $student) {
                    $stats['errors']++;
                    $details[] = [
                        'row' => $lineNumber,
                        'student_id' => $studentId,
                        'voucher_code' => implode(',', $voucherCodes),
                        'status' => 'error',
                        'message' => 'Student not found',
                    ];

                    continue;
                }

                foreach ($voucherCodes as $voucherCode) {
                    if (empty($voucherCode)) {
                        continue;
                    }

                    $stats['total']++;

                    try {
                        // Find voucher
                        $voucher = VoucherDefinition::where('code', $voucherCode)->first();
                        if (! $voucher) {
                            $stats['errors']++;
                            $details[] = [
                                'row' => $lineNumber,
                                'student_id' => $studentId,
                                'voucher_code' => $voucherCode,
                                'status' => 'error',
                                'message' => 'Voucher not found',
                            ];

                            continue;
                        }

                        // Validate voucher
                        if (! $voucher->is_active) {
                            $stats['errors']++;
                            $details[] = [
                                'row' => $lineNumber,
                                'student_id' => $studentId,
                                'voucher_code' => $voucherCode,
                                'status' => 'error',
                                'message' => 'Voucher is not active',
                            ];

                            continue;
                        }

                        $now = now();
                        if ($now->lessThan($voucher->valid_from) || $now->greaterThan($voucher->valid_until)) {
                            $stats['errors']++;
                            $details[] = [
                                'row' => $lineNumber,
                                'student_id' => $studentId,
                                'voucher_code' => $voucherCode,
                                'status' => 'error',
                                'message' => "Voucher is not valid (valid from {$voucher->valid_from->format('Y-m-d')} to {$voucher->valid_until->format('Y-m-d')})",
                            ];

                            continue;
                        }

                        // Check for duplicate
                        $existing = VoucherRedemption::where('student_id', $student->id)
                            ->where('voucher_id', $voucher->id)
                            ->where('billing_cycle_id', $billingCycleId)
                            ->first();

                        if ($existing) {
                            $stats['skipped']++;
                            $details[] = [
                                'row' => $lineNumber,
                                'student_id' => $studentId,
                                'voucher_code' => $voucherCode,
                                'status' => 'skipped',
                                'message' => 'Voucher already redeemed for this billing cycle',
                            ];

                            continue;
                        }

                        // Create redemption
                        VoucherRedemption::create([
                            'voucher_id' => $voucher->id,
                            'student_id' => $student->id,
                            'billing_cycle_id' => $billingCycleId,
                            'invoice_id' => null,
                            'status' => 'pending',
                            'redeemed_at' => null,
                        ]);

                        $stats['created']++;
                        $details[] = [
                            'row' => $lineNumber,
                            'student_id' => $studentId,
                            'voucher_code' => $voucherCode,
                            'status' => 'success',
                            'message' => 'Voucher redemption created successfully',
                        ];
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $details[] = [
                            'row' => $lineNumber,
                            'student_id' => $studentId,
                            'voucher_code' => $voucherCode,
                            'status' => 'error',
                            'message' => 'Error: '.$e->getMessage(),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to process Excel file: '.$e->getMessage());
        }

        return [
            'success' => true,
            'stats' => $stats,
            'details' => $details,
        ];
    }
}
