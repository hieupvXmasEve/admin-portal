<?php

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FinanceCharge as FinanceChargeModel;
use App\Models\Payment;
use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\PreviewPaymentImportAction;
use App\Modules\Finance\Actions\StorePaymentImportAction;
// Assuming standard model location or need alias?
// Wait, Models are in App\Models based on previous checks.
use App\Modules\Finance\Queries\GetPaymentDetailsQuery;
use App\Modules\Finance\Queries\ListPaymentsQuery;
use App\Modules\Finance\Queries\Operations\PreviewAutoAllocateQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request, ListPaymentsQuery $query)
    {
        return Inertia::render('Finance/Payments/Index', [
            'items' => $query->handle($request),
            'filters' => $request->only(['search', 'source', 'status', 'date_range', 'per_page']),
        ]);
    }

    public function show(int $id, GetPaymentDetailsQuery $query)
    {
        return Inertia::render('Finance/Payments/Show', [
            'payment' => $query->handle($id),
        ]);
    }

    public function import()
    {
        return Inertia::render('Finance/Payments/Import');
    }

    public function previewImport(Request $request, PreviewPaymentImportAction $action)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $result = $action->run($request->file('file'));

        return ApiResponse::success($result, [], 'File preview generated successfully');
    }

    public function storeImport(Request $request, StorePaymentImportAction $action)
    {
        $request->validate([
            'rows' => 'required|array',
            'rows.*.student_id' => 'required|exists:students,id',
            'rows.*.amount' => 'required|numeric|min:0',
            'rows.*.paid_at' => 'required|date',
            // other validation
        ]);

        $result = $action->run($request->input('rows'), $request->user()->id);

        $importedCount = $result['imported_count'] ?? 0;

        return ApiResponse::success($result, [], "Successfully imported {$importedCount} payments");
    }

    public function allocate(int $id, Request $request, AllocatePaymentAction $action)
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'charge_id' => 'required|exists:finance_charges,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $charge = FinanceChargeModel::findOrFail($request->input('charge_id'));

        $action->run(
            $payment,
            $charge,
            (float) $request->input('amount'),
            $request->user()->id
        );

        return back()->with('success', 'Payment allocated successfully.');
    }

    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PaymentImportTemplateExport, 'payment_import_template.xlsx');
    }

    public function showAutoAllocate()
    {
        return Inertia::render('Finance/Payments/AutoAllocate');
    }

    public function previewAutoAllocate(Request $request, PreviewAutoAllocateQuery $query)
    {
        $request->validate([
            'priority_order' => 'required|array',
            'priority_order.*' => 'string',
        ]);

        $preview = $query->handle($request->input('priority_order'));

        return response()->json($preview);
    }

    public function autoAllocate(Request $request, \App\Modules\Finance\Actions\AutoAllocatePaymentsAction $action)
    {
        $request->validate([
            'priority_order' => 'required|array',
            'priority_order.*' => 'string',
        ]);

        $stats = $action->run($request->input('priority_order'), $request->user()->id);

        $message = "Auto-allocation complete. Processed {$stats['students_processed']} students, created {$stats['allocations_created']} allocations.";
        if (isset($stats['invoices_updated']) && $stats['invoices_updated'] > 0) {
            $message .= " Updated {$stats['invoices_updated']} invoices.";
        }

        return redirect()->route('finance.payments.index')
            ->with('success', $message);
    }
}
