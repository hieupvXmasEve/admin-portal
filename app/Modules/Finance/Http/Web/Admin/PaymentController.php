<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Http\Requests\Lookup\FilterPaymentsRequest;
use App\Modules\Finance\Models\FinanceCharge as FinanceChargeModel;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\GetPaymentDetailsQuery;
use App\Modules\Finance\Queries\ListPaymentsQuery;
use App\Modules\Finance\Queries\Operations\PreviewAutoAllocateQuery;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private DngCampusCodeResolver $dngCampusCodeResolver,
        private StudentReferenceReader $studentReferences,
    ) {}

    public function index(FilterPaymentsRequest $request, ListPaymentsQuery $query): Response
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
            'semesters' => $this->buildSemesterOptions(),
            'feeTypes' => DngFeeTypeOptions::all(),
        ]);
    }

    /**
     * Return student data pre-filled for DNG payment form.
     */
    public function getStudentDngData(int $studentId)
    {
        $student = $this->visibleStudentReference($studentId);
        abort_if($student === null, 404);

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
    private function buildStudentDngData(StudentReference $student): array
    {
        $latestDngRequest = DngPaymentRequest::query()
            ->where('student_id', $student->id)
            ->latest('created_at')
            ->first();

        return [
            'student_id' => $student->id,
            'campus_code' => $this->dngCampusCodeResolver->requireForCampusId($student->campusId),
            'student_code' => $student->studentCode,
            'student_name' => $student->fullName,
            'email' => $student->email ?? '',
            'student_address' => $student->address ?? '',
            'cccd' => $student->nationalId ?? '',
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
     *     semester_id: int|null,
     *     due_date: string,
     *     source_context: string|null,
     * }|null
     */
    private function buildCreatePrefill(Request $request): ?array
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'fee_type' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'due_date' => ['nullable', 'date'],
            'source_context' => ['nullable', 'string', 'max:50'],
        ]);

        if (! isset($validated['student_id'])) {
            return null;
        }

        $student = $this->visibleStudentReference((int) $validated['student_id']);

        if (! $student) {
            return null;
        }

        return [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->studentCode,
                'full_name' => $student->fullName,
                'email' => $student->email ?? '',
            ],
            'dng_data' => $this->buildStudentDngData($student),
            'amount' => isset($validated['amount']) ? (float) $validated['amount'] : null,
            'fee_type' => (string) ($validated['fee_type'] ?? 'HP'),
            'description' => (string) ($validated['description'] ?? ''),
            'semester_id' => isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
            'due_date' => isset($validated['due_date']) ? (string) $validated['due_date'] : '',
            'source_context' => $validated['source_context'] ?? null,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function buildSemesterOptions(): array
    {
        return Semester::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'code' => $semester->code,
            ])
            ->all();
    }

    private function visibleStudentReference(int $studentId): ?StudentReference
    {
        $student = $this->studentReferences->find($studentId);
        $campusId = app()->bound('campus') ? app('campus')?->id : null;

        return $student !== null && $campusId !== null && $student->campusId === (int) $campusId
            ? $student
            : null;
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

    public function autoAllocate(Request $request, AutoAllocatePaymentsAction $action)
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
