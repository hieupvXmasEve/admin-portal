<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Actions\Operations\GenerateNonAcademicChargesAction;
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Actions\Operations\SendParentPaymentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendPaymentRemindersAction;
use App\Modules\Finance\Exports\GenerateChargesPreviewExport;
use App\Modules\Finance\Http\Requests\GenerateNonAcademicChargesRequest;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BillingOperationsController extends Controller
{
    /**
     * @deprecated Bulk EGC flow moved to Finance/EgcOperations. Retained per Q9 decision.
     *             Do not call from new code. Remove after confirming no consumers.
     */
    public function previewCharges(Request $request, PreviewChargeGenerationQuery $query)
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
            'scope_type' => 'required|string|in:all_eligible,by_filter,upload_list',
            'filter_program_id' => 'nullable|integer|exists:programs,id',
            'filter_enrollment_status' => 'nullable|string',
            'uploaded_student_ids' => 'nullable|array',
            'uploaded_student_ids.*' => 'string',
            'charge_types' => 'required|array|min:1',
            'charge_types.*' => 'string',
            'custom_amount' => 'nullable|numeric|min:0',
            'skip_if_issued_or_paid' => 'boolean',
            'only_update_draft' => 'boolean',
            'merge_invoice' => 'boolean',
        ]);

        $result = $query->handle($validated);

        return ApiResponse::success($result);
    }

    /**
     * @deprecated Bulk EGC flow moved to Finance/EgcOperations. Retained per Q9 decision.
     *             Do not call from new code. Remove after confirming no consumers.
     */
    public function exportPreviewCharges(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'students' => 'required|array|min:1',
            'students.*.id' => 'required|integer',
            'students.*.student_id' => 'required|string',
            'students.*.full_name' => 'required|string',
            'students.*.status' => 'required|string',
            'students.*.student_type' => 'nullable|string',
            'students.*.has_existing_charge' => 'boolean',
            'students.*.estimated_amount' => 'required|numeric',
            'students.*.warning' => 'nullable|string',
            'students.*.breakdown' => 'nullable|array',
            'students.*.breakdown.*.label' => 'string',
            'students.*.breakdown.*.amount' => 'numeric',
            'students.*.will_create_invoice' => 'boolean',
        ]);

        $export = new GenerateChargesPreviewExport($validated['students']);
        $filename = 'generate_charges_preview_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * @deprecated Bulk EGC flow moved to Finance/EgcOperations. Retained per Q9 decision.
     *             Do not call from new code. Remove after confirming no consumers.
     */
    public function runGenerate(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
            'scope_type' => 'required|string|in:all_eligible,by_filter,upload_list',
            'filter_program_id' => 'nullable|integer|exists:programs,id',
            'filter_enrollment_status' => 'nullable|string',
            'uploaded_student_ids' => 'nullable|array',
            'uploaded_student_ids.*' => 'string',
            'charge_types' => 'required|array|min:1',
            'charge_types.*' => 'string',
            'custom_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'skip_if_issued_or_paid' => 'boolean',
            'only_update_draft' => 'boolean',
            'merge_invoice' => 'boolean',
        ]);

        $result = GenerateBatchChargesAction::run($validated);

        return ApiResponse::success($result);
    }

    /**
     * Generate non-academic charges from a CSV upload of student codes.
     *
     * Authorization enforced by GenerateNonAcademicChargesRequest::authorize() (F2).
     * Response shape: ApiResponse::success({ created, skipped, summary }).
     * Vue reads result as response.value.data.data (F4 — ApiResponse envelope).
     */
    public function generateNonAcademic(GenerateNonAcademicChargesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Parse CSV → unique student code array (trim whitespace, skip blank rows, skip header).
        // Read up to MAX_ROWS + 1 to detect overflow — reject with 422 if exceeded.
        $csvFile = $request->file('csv_file');
        $handle = fopen($csvFile->getRealPath(), 'r');
        $studentCodes = [];
        $headerSkipped = false;
        $maxRows = 1000;

        while (($row = fgetcsv($handle)) !== false) {
            if (! $headerSkipped) {
                $headerSkipped = true;
                $firstCell = trim($row[0] ?? '');

                // Detect header rows: any cell that does NOT match the valid student code
                // format ([A-Z0-9]{4,20}) is treated as a header label.
                // If it IS a header label, enforce that it must be exactly "student_code".
                // This blocks files with wrong headers (e.g. "code") rather than silently
                // accepting them and producing confusing skip-all results.
                $looksLikeData = preg_match('/^[A-Z0-9]{4,20}$/', $firstCell);

                if (! $looksLikeData && $firstCell !== '') {
                    // First cell is a header label
                    if (strtolower($firstCell) !== 'student_code') {
                        fclose($handle);
                        throw ValidationException::withMessages([
                            'csv_file' => ["Header CSV không hợp lệ. Cột đầu tiên phải là 'student_code'."],
                        ]);
                    }

                    // Valid "student_code" header — skip this row.
                    continue;
                }

                // First row contains data (no header) — fall through to process it.
            }

            $code = isset($row[0]) ? trim($row[0]) : '';
            if ($code !== '') {
                $studentCodes[] = $code;
            }

            // Read one extra row beyond the limit to detect overflow without loading the whole file.
            if (count($studentCodes) > $maxRows) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'csv_file' => ["File CSV không được vượt quá {$maxRows} dòng dữ liệu."],
                ]);
            }
        }

        fclose($handle);

        $result = GenerateNonAcademicChargesAction::run([
            'fee_type' => $validated['fee_type'],
            'semester_id' => (int) $validated['semester_id'],
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'],
            'note' => $validated['note'] ?? '',
            'student_codes' => array_values(array_unique($studentCodes)),
        ]);

        return ApiResponse::success($result);
    }

    public function fixException(Request $request, int $exceptionId): JsonResponse
    {
        try {
            $result = FixBillingExceptionAction::run([
                'exception_id' => $exceptionId,
                'semester_id' => $request->integer('semester_id'),
            ]);

            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function sendReminders(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:student_invoices,id',
        ]);

        $result = SendPaymentRemindersAction::run($validated);

        return ApiResponse::success($result);
    }

    public function sendParentReminders(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer|exists:student_invoices,id',
        ]);

        $result = SendParentPaymentRemindersAction::run($validated);

        return ApiResponse::success($result);
    }

    public function sendDueItemReminders(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'string',
        ]);

        $result = SendDueItemRemindersAction::run($validated);

        if ($result['sent_count'] > 0) {
            Inertia::flash('success', $result['message']);
        } else {
            Inertia::flash('warning', $result['message']);
        }

        return back();
    }

    public function sendDueItemParentReminders(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'string',
        ]);

        $result = SendDueItemParentRemindersAction::run($validated);

        if ($result['sent_count'] > 0) {
            Inertia::flash('success', $result['message']);
        } else {
            Inertia::flash('warning', $result['message']);
        }

        return back();
    }
}
