<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Models\Campus;
use App\Models\Student;
use App\Models\StudentCashWallet;
use App\Models\WalletTransaction;
use App\Services\CashWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentCashWalletController extends Controller
{
    protected int $batchSize = 100;
    protected float $maxAmount = 1000000000; // 1 billion VND

    public function __construct(
        private CashWalletService $walletService
    ) {}

    /**
     * Display a listing of student wallets.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'min_balance' => 'nullable|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = StudentCashWallet::query()
            ->with(['student:id,student_id,full_name,campus_id', 'student.campus:id,name'])
            ->withCount('transactions');

        // Add last transaction date as a computed column
        $query->addSelect([
            'last_transaction_date' => WalletTransaction::select('created_at')
                ->whereColumn('wallet_id', 'student_cash_wallets.id')
                ->latest()
                ->limit(1)
        ]);

        // Apply search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        // Apply campus filter
        if (!empty($validated['campus_id'])) {
            $query->whereHas('student', function ($q) use ($validated) {
                $q->where('campus_id', $validated['campus_id']);
            });
        }

        // Apply balance filters
        if (isset($validated['min_balance'])) {
            $query->where('balance', '>=', $validated['min_balance']);
        }

        if (isset($validated['max_balance'])) {
            $query->where('balance', '<=', $validated['max_balance']);
        }

        $wallets = $query->orderBy('updated_at', 'desc')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        return Inertia::render('fee/wallet/Index', [
            'wallets' => $wallets,
            'campuses' => $campuses,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'campus_id' => $validated['campus_id'] ?? null,
                'min_balance' => $validated['min_balance'] ?? null,
                'max_balance' => $validated['max_balance'] ?? null,
                'per_page' => $validated['per_page'] ?? 20,
            ],
        ]);
    }

    /**
     * Display the specified wallet.
     */
    public function show(Request $request, StudentCashWallet $wallet): Response
    {
        $validated = $request->validate([
            'transaction_type' => 'nullable|string|in:all,deposit,payment,refund,adjustment',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $wallet->load(['student:id,student_id,full_name,email,campus_id,avatar_url', 'student.campus:id,name']);

        $transactionsQuery = $wallet->transactions()
            ->with('createdBy:id,name');

        if (!empty($validated['transaction_type']) && $validated['transaction_type'] !== 'all') {
            $transactionsQuery->where('transaction_type', $validated['transaction_type']);
        }

        $transactions = $transactionsQuery
            ->latest()
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        $stats = $this->walletService->getWalletStats($wallet->id);

        $lastTransaction = $wallet->transactions()->latest()->first();

        return Inertia::render('fee/wallet/Show', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'stats' => $stats,
            'lastTransaction' => $lastTransaction,
            'filters' => [
                'transaction_type' => $validated['transaction_type'] ?? 'all',
                'per_page' => $validated['per_page'] ?? 15,
            ],
        ]);
    }

    /**
     * Process a deposit to the student's wallet.
     */
    public function deposit(DepositRequest $request, StudentCashWallet $wallet): RedirectResponse
    {
        try {
            $transaction = $this->walletService->deposit(
                $wallet->id,
                $request->validated('amount'),
                $request->validated('description')
            );

            return redirect()->back()->with('success', 'Deposit processed successfully. Transaction ID: ' . $transaction->id);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process a balance adjustment (positive or negative).
     */
    public function adjustment(Request $request, StudentCashWallet $wallet): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        try {
            $transaction = $this->walletService->adjustment(
                $wallet->id,
                $request->amount,
                $request->description
            );

            $type = $request->amount > 0 ? 'credit' : 'debit';

            return redirect()->back()->with('success', "Balance {$type} adjustment processed successfully. Transaction ID: " . $transaction->id);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show import page.
     */
    public function import(): Response
    {
        return Inertia::render('fee/wallet/Import');
    }

    /**
     * Preview import data before processing.
     */
    public function preview(Request $request): Response
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $previewData = $this->generatePreview($file);

            // Store file path in session for processing later
            $filePath = $file->store('temp/imports');
            session(['wallet_import_file' => $filePath]);

            return Inertia::render('fee/wallet/Import', [
                'preview' => $previewData,
            ]);
        } catch (\Exception $e) {
            Log::error('Preview failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to preview file: ' . $e->getMessage()]);
        }
    }

    /**
     * Process the import after preview confirmation.
     */
    public function process(Request $request): RedirectResponse
    {
        $filePath = session('wallet_import_file');

        if (!$filePath || !Storage::exists($filePath)) {
            return redirect()->route('wallets.import')->withErrors(['error' => 'No file found. Please upload again.']);
        }

        try {
            $fullPath = Storage::path($filePath);
            $results = $this->processDepositImport($fullPath);

            // Clear session
            session()->forget('wallet_import_file');
            Storage::delete($filePath);

            if ($results['successful'] > 0) {
                $message = "Successfully processed {$results['successful']} deposits";
                if ($results['failed'] > 0) {
                    $message .= " ({$results['failed']} failed)";
                }
                return redirect()->route('wallets.index')->with('success', $message);
            }

            return redirect()->route('wallets.import')->withErrors(['error' => 'No deposits were processed']);
        } catch (\Exception $e) {
            Log::error('Import process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->forget('wallet_import_file');
            if ($filePath) {
                Storage::delete($filePath);
            }

            return redirect()->route('wallets.import')->withErrors(['error' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Download template for bulk import.
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Student ID', 'Amount', 'Description'];
            $sheet->fromArray($headers, null, 'A1');

            // Add sample data
            $sampleData = [
                ['SV001', 5000000, 'Tuition payment'],
                ['SV002', 3000000, 'Semester fee'],
                ['SV003', 10000000, 'Full payment'],
            ];
            $sheet->fromArray($sampleData, null, 'A2');

            // Auto-size columns
            foreach (range('A', 'C') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'wallet_deposit_template_' . date('Y-m-d') . '.xlsx';
            $tempPath = storage_path('app/temp/templates');

            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $fullPath = $tempPath . '/' . $filename;

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($fullPath);

            return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Template download failed', ['error' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => 'Failed to generate template']);
        }
    }

    /**
     * Generate preview data from uploaded file.
     */
    protected function generatePreview(UploadedFile $file): array
    {
        $fullPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getSheet(0);

        $rows = [];
        $summary = [
            'total' => 0,
            'valid' => 0,
            'errors' => 0,
            'warnings' => 0,
        ];

        $startRow = 2;
        $endRow = min($worksheet->getHighestRow(), $startRow + 999); // Preview max 1000 rows

        for ($row = $startRow; $row <= $endRow; $row++) {
            $studentId = trim((string) $worksheet->getCell("A{$row}")->getCalculatedValue());
            $amountValue = $worksheet->getCell("B{$row}")->getCalculatedValue();
            $description = trim((string) $worksheet->getCell("C{$row}")->getCalculatedValue());

            // Skip empty rows
            if (empty($studentId) && empty($amountValue)) {
                continue;
            }

            $summary['total']++;

            $rowData = [
                'row' => $row,
                'student_id' => $studentId,
                'amount' => null,
                'description' => $description ?: 'Bulk deposit from Excel',
                'status' => 'valid',
                'message' => null,
                'student_name' => null,
            ];

            // Validate student ID
            if (empty($studentId)) {
                $rowData['status'] = 'error';
                $rowData['message'] = 'Student ID is required';
                $summary['errors']++;
            } else {
                $student = Student::where('student_id', $studentId)->first();
                if (!$student) {
                    $rowData['status'] = 'error';
                    $rowData['message'] = 'Student not found';
                    $summary['errors']++;
                } else {
                    $rowData['student_name'] = $student->full_name;
                }
            }

            // Validate amount
            if (empty($amountValue) || !is_numeric($amountValue)) {
                $rowData['status'] = 'error';
                $rowData['message'] = $rowData['message'] ? $rowData['message'] . ', Invalid amount' : 'Invalid amount';
                $summary['errors']++;
            } else {
                $amount = (float) $amountValue;
                $rowData['amount'] = $amount;

                if ($amount <= 0) {
                    $rowData['status'] = 'error';
                    $rowData['message'] = $rowData['message'] ? $rowData['message'] . ', Amount must be positive' : 'Amount must be positive';
                    $summary['errors']++;
                } elseif ($amount < 1000) {
                    $rowData['status'] = 'error';
                    $rowData['message'] = $rowData['message'] ? $rowData['message'] . ', Amount too small (min: 1,000 VND)' : 'Amount too small (min: 1,000 VND)';
                    $summary['errors']++;
                } elseif ($amount > $this->maxAmount) {
                    $rowData['status'] = 'error';
                    $rowData['message'] = $rowData['message'] ? $rowData['message'] . ', Amount exceeds maximum' : 'Amount exceeds maximum';
                    $summary['errors']++;
                }
            }

            if ($rowData['status'] === 'valid') {
                $rowData['message'] = 'Ready to import';
                $summary['valid']++;
            }

            $rows[] = $rowData;
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * Process deposit import from Excel file.
     */
    protected function processDepositImport(string $fullPath): array
    {

        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getSheet(0);

        $results = [
            'total_rows' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        $startRow = 2;
        $endRow = $worksheet->getHighestRow();

        for ($batchStart = $startRow; $batchStart <= $endRow; $batchStart += $this->batchSize) {
            $batchEnd = min($batchStart + $this->batchSize - 1, $endRow);

            try {
                DB::transaction(function () use ($worksheet, $batchStart, $batchEnd, &$results) {
                    for ($row = $batchStart; $row <= $batchEnd; $row++) {
                        try {
                            $studentIdValue = $worksheet->getCell("A{$row}")->getCalculatedValue();
                            $amountValue = $worksheet->getCell("B{$row}")->getCalculatedValue();
                            $descriptionValue = $worksheet->getCell("C{$row}")->getCalculatedValue();

                            // Skip empty rows
                            if (empty($studentIdValue) && (empty($amountValue) || $amountValue === null)) {
                                continue;
                            }

                            $results['total_rows']++;

                            $studentId = trim((string) ($studentIdValue ?? ''));
                            $amount = $amountValue;
                            $description = trim((string) ($descriptionValue ?? ''));

                            // Validate required fields
                            if (empty($studentId)) {
                                $results['skipped']++;
                                $results['warnings'][] = "Row {$row}: Student ID is required";
                                continue;
                            }

                            if (empty($amount) || $amount <= 0) {
                                $results['skipped']++;
                                $results['warnings'][] = "Row {$row}: Valid amount is required";
                                continue;
                            }

                            // Find student
                            $student = Student::where('student_id', $studentId)->first();
                            if (!$student) {
                                $results['failed']++;
                                $results['errors'][] = "Row {$row}: Student ID {$studentId} not found";
                                continue;
                            }

                            // Validate amount
                            $this->validateDepositAmount($amount, $row);

                            // Process deposit
                            $wallet = StudentCashWallet::lockForUpdate()
                                ->firstOrCreate(
                                    ['student_id' => $student->id],
                                    ['balance' => 0, 'currency' => 'VND']
                                );

                            $balanceBefore = $wallet->balance;
                            $balanceAfter = $balanceBefore + $amount;

                            // Create transaction
                            WalletTransaction::create([
                                'wallet_id' => $wallet->id,
                                'transaction_type' => 'deposit',
                                'amount' => $amount,
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'description' => $description ?: 'Bulk deposit from Excel',
                                'created_by' => auth()->id(),
                            ]);

                            // Update wallet balance
                            $wallet->update(['balance' => $balanceAfter]);

                            $results['successful']++;
                        } catch (\Exception $e) {
                            $results['failed']++;
                            $results['errors'][] = "Row {$row}: " . $e->getMessage();
                        }
                    }
                });
            } catch (\Exception $e) {
                $results['failed'] += ($batchEnd - $batchStart + 1);
                $results['errors'][] = "Batch {$batchStart}-{$batchEnd}: " . $e->getMessage();
                break;
            }
        }

        return $results;
    }

    /**
     * Validate deposit amount.
     */
    protected function validateDepositAmount(float $amount, int $row): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Row {$row}: Amount must be positive");
        }

        if ($amount > $this->maxAmount) {
            throw new \InvalidArgumentException(
                "Row {$row}: Amount exceeds maximum limit of " . number_format($this->maxAmount, 0, '.', ',') . " VND"
            );
        }

        if ($amount < 1000) {
            throw new \InvalidArgumentException(
                "Row {$row}: Amount too small (minimum: 1,000 VND)"
            );
        }
    }
}
