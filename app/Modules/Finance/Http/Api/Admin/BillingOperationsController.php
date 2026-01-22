<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Actions\Operations\SendPaymentRemindersAction;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use Illuminate\Http\Request;

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
}
