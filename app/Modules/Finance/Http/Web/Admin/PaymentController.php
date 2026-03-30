<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FinanceCharge as FinanceChargeModel;
use App\Models\Payment;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\PreviewPaymentImportAction;
use App\Modules\Finance\Actions\StorePaymentImportAction;
use App\Modules\Finance\Queries\GetPaymentDetailsQuery;
use App\Modules\Finance\Queries\ListPaymentsQuery;
use App\Modules\Finance\Queries\Operations\PreviewAutoAllocateQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, ListPaymentsQuery $query)
    {
        $result = $query->handle($request);

        return Inertia::render('Finance/Payments/Index', [
            'items' => $result['items'],
            'stats' => $result['stats'],
            'filters' => $request->only(['search', 'source', 'status', 'date_range', 'per_page', 'sort', 'direction']),
        ]);
    }

    public function show(int $id, GetPaymentDetailsQuery $query)
    {
        return Inertia::render('Finance/Payments/Show', [
            'payment' => $query->handle($id),
        ]);
    }

    /**
     * Render the Create Payment page (DNG gateway flow).
     * Actual payment creation goes through DNG API: POST /api/v1/finance/dng/payment-requests
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Finance/Payments/Create', [
            'prefill' => $this->buildCreatePrefill($request),
        ]);
    }

    /**
     * Return student data pre-filled for DNG payment form.
     */
    public function getStudentDngData(int $studentId)
    {
        $studentQuery = Student::query()->whereKey($studentId);
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus !== null && isset($campus->id)) {
            $studentQuery->where('campus_id', (int) $campus->id);
        }

        $student = $studentQuery->firstOrFail();

        return response()->json($this->buildStudentDngData($student));
    }

    /**
     * @return array{
     *     student_id: int,
     *     campus_code: string,
     *     student_code: string,
     *     student_name: string,
     *     email: string,
     *     student_address: string,
     *     cccd: string,
     *     latest_dng_request: array{id: int, status: string, item_id: string, description: string|null, created_at: string|null}|null,
     * }
     */
    private function buildStudentDngData(Student $student): array
    {
        $latestDngRequest = DngPaymentRequest::query()
            ->where('student_id', $student->id)
            ->latest('created_at')
            ->first();

        return [
            'student_id' => $student->id,
            'campus_code' => (string) config('services.dng.campus_code'),
            'student_code' => $student->student_id,
            'student_name' => $student->full_name,
            'email' => $student->email ?? '',
            'student_address' => $student->address ?? $student->current_address_line ?? '',
            'cccd' => $student->national_id ?? '',
            'latest_dng_request' => $latestDngRequest ? [
                'id' => $latestDngRequest->id,
                'status' => $latestDngRequest->status,
                'item_id' => $latestDngRequest->item_id,
                'description' => $latestDngRequest->description,
                'created_at' => $latestDngRequest->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array{
     *     student: array{id: int, student_id: string, full_name: string, email: string},
     *     dng_data: array{
     *         student_id: int,
     *         campus_code: string,
     *         student_code: string,
     *         student_name: string,
     *         email: string,
     *         student_address: string,
     *         cccd: string,
     *         latest_dng_request: array{id: int, status: string, item_id: string, description: string|null, created_at: string|null}|null,
     *     },
     *     amount: float|null,
     *     fee_type: string,
     *     description: string,
     *     source_context: string|null,
     * }|null
     */
    private function buildCreatePrefill(Request $request): ?array
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'fee_type' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'source_context' => ['nullable', 'string', 'max:50'],
        ]);

        if (! isset($validated['student_id'])) {
            return null;
        }

        $studentQuery = Student::query()->whereKey((int) $validated['student_id']);
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus !== null && isset($campus->id)) {
            $studentQuery->where('campus_id', (int) $campus->id);
        }

        $student = $studentQuery->first();

        if (! $student) {
            return null;
        }

        return [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email ?? '',
            ],
            'dng_data' => $this->buildStudentDngData($student),
            'amount' => isset($validated['amount']) ? (float) $validated['amount'] : null,
            'fee_type' => (string) ($validated['fee_type'] ?? 'HP'),
            'description' => (string) ($validated['description'] ?? ''),
            'source_context' => $validated['source_context'] ?? null,
        ];
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
        return redirect()->route('finance.operations.settlement.index');
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
