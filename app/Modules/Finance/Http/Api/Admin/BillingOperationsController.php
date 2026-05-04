<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Inertia\Inertia;
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Actions\Operations\SendParentPaymentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendPaymentRemindersAction;
use App\Modules\Finance\Exports\GenerateChargesPreviewExport;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BillingOperationsController extends Controller
{
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

    public function fixException(Request $request, int $exceptionId)
    {
        // Need to validate/pass exception ID? Action might need it.
        // Assuming Action handles logic with ID.
        $result = FixBillingExceptionAction::run(['exception_id' => $exceptionId]);

        return ApiResponse::success($result);
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

        // Use flash messages for Inertia
        if ($result['sent_count'] > 0) {
            Inertia::flash('success', $result['message']);
        } else {
            Inertia::flash('warning', $result['message']);
        }

        // Return back for Inertia requests
        return back();
    }

    public function sendDueItemParentReminders(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'string',
        ]);

        $result = SendDueItemParentRemindersAction::run($validated);

        // Use flash messages for Inertia
        if ($result['sent_count'] > 0) {
            Inertia::flash('success', $result['message']);
        } else {
            Inertia::flash('warning', $result['message']);
        }

        // Return back for Inertia requests
        return back();
    }
}
