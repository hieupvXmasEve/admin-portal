<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateStaffDebitAction;
use App\Modules\Finance\Actions\PushNextInstallmentAction;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Exceptions\ChargeHasPaidInstallmentException;
use App\Modules\Finance\Exceptions\InstallmentSplitNotAllowedException;
use App\Modules\Finance\Exceptions\InvalidInstallmentPlanException;
use App\Modules\Finance\Http\Requests\Charges\SplitChargeIntoInstallmentsRequest;
use App\Modules\Finance\Http\Requests\Charges\StoreFinanceChargeRequest;
use App\Modules\Finance\Http\Requests\Lookup\FilterFinanceChargesRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Queries\Lookup\ListFinanceChargesQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceChargeController extends Controller
{
    /** Wave 7: staff form is intake-only for staff-supplied debits. */
    private const MANUAL_CREATE_CHARGE_TYPES = [
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_ADJUSTMENT,
    ];

    private const MANUAL_CREATE_CHARGE_TYPE_DNG_LABELS = [
        FinanceCharge::TYPE_MANUAL_FEE => 'KHAC',
        FinanceCharge::TYPE_ADMISSION_FEE => 'PRE',
        FinanceCharge::TYPE_ADJUSTMENT => 'KHAC',
    ];

    public function __construct(
        private CreateStaffDebitAction $createStaffDebitAction,
        private VoidFinanceChargeAction $voidChargeAction,
    ) {}

    /**
     * Display a listing of finance charges.
     */
    public function index(
        FilterFinanceChargesRequest $request,
        ListFinanceChargesQuery $query,
    ): Response {
        return $this->renderIndex($request, $query);
    }

    /**
     * Display a listing of finance charges for a specific student.
     */
    public function studentCharges(
        FilterFinanceChargesRequest $request,
        ListFinanceChargesQuery $query,
        Student $student,
    ): Response {
        return $this->renderIndex($request, $query, $student);
    }

    private function renderIndex(
        FilterFinanceChargesRequest $request,
        ListFinanceChargesQuery $query,
        ?Student $student = null,
    ): Response {
        $selectedStudent = $student;

        if (! $selectedStudent && $request->filled('student_id')) {
            $selectedStudent = Student::query()->find((int) $request->input('student_id'));
        }

        $result = $query->handle($request, $selectedStudent?->id);

        $semesters = Semester::orderBy('start_date', 'desc')->get();

        $chargeTypes = collect(self::MANUAL_CREATE_CHARGE_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => ucwords(str_replace('_', ' ', $type)),
        ]);

        return Inertia::render('Finance/Charges/Index', [
            'charges' => $result['items'],
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'student' => $selectedStudent ? [
                'id' => $selectedStudent->id,
                'full_name' => $selectedStudent->full_name,
                'student_id' => $selectedStudent->student_id,
            ] : null,
            'filters' => [
                'search' => $request->input('search', ''),
                'student_id' => $selectedStudent?->id ?? ($request->filled('student_id') ? (int) $request->input('student_id') : null),
                'semester_id' => FinanceSemesterContextResolver::selectedId(),
                'charge_type' => $request->input('charge_type', 'all'),
                'status' => $request->input('status', 'all'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'per_page' => (int) $request->input('per_page', 20),
            ],
        ]);
    }

    /**
     * Display the specified charge.
     */
    public function show(FinanceCharge $charge): Response
    {
        $charge->load([
            'student',
            'semester',
            'billingCycle',
            'createdBy',
            'voidedBy',
            'invoiceLines.paymentApplications.payment',
            'invoiceLines.invoice',
            'installments',
        ]);

        $netSplitTarget = max(0, (float) $charge->amount - (float) $charge->discount_amount);

        return Inertia::render('Finance/Charges/Show', [
            'charge' => [
                ...$charge->toArray(),
                'created_by' => $charge->createdBy ? [
                    'id' => $charge->createdBy->id,
                    'name' => $charge->createdBy->name,
                    'email' => $charge->createdBy->email,
                ] : null,
                'voided_by' => $charge->voidedBy ? [
                    'id' => $charge->voidedBy->id,
                    'name' => $charge->voidedBy->name,
                    'email' => $charge->voidedBy->email,
                ] : null,
            ],
            'installments' => $charge->installments->map(fn ($i) => [
                'id' => $i->id,
                'installment_no' => $i->installment_no,
                'amount' => $i->amount,
                'due_date' => $i->due_date?->toDateString(),
                'status' => $i->status,
                'dng_payment_request_id' => $i->dng_payment_request_id,
                'paid_at' => $i->paid_at?->toIso8601String(),
                'push_attempt_count' => $i->push_attempt_count,
                'last_push_error' => $i->last_push_error,
                'has_push_error' => $i->has_push_error,
            ]),
            'installment_meta' => [
                'net_split_target' => $netSplitTarget,
                'has_paid_installment' => $charge->hasPaidInstallment(),
                'can_split' => $charge->status === FinanceCharge::STATUS_ACTIVE
                    && (float) $charge->amount > 0
                    && $netSplitTarget > 0
                    && ! $charge->hasPaidInstallment(),
            ],
        ]);
    }

    /**
     * Replace a charge's installment plan with the submitted N-row plan.
     * Domain invariants enforced inside SplitChargeIntoInstallmentsAction.
     */
    public function splitInstallments(
        SplitChargeIntoInstallmentsRequest $request,
        FinanceCharge $charge,
        SplitChargeIntoInstallmentsAction $action,
    ): RedirectResponse {
        $this->authorize('splitInstallment', $charge);

        try {
            $action->handle($charge->id, $request->validated()['installments']);
        } catch (ChargeHasPaidInstallmentException $e) {
            return back()->withErrors(['installments' => $e->getMessage()]);
        } catch (InstallmentSplitNotAllowedException $e) {
            return back()->withErrors(['installments' => $e->getMessage()]);
        } catch (InvalidInstallmentPlanException $e) {
            return back()->withErrors(['installments' => $e->getMessage()]);
        }

        Inertia::flash('success', 'Kế hoạch đợt đã được lưu.');

        return back();
    }

    /**
     * Retry pushing an installment to DNG after a previous push failed.
     * Only allowed when installment is pending with last_push_error set.
     */
    public function retryPushInstallment(
        FinanceCharge $charge,
        FinanceChargeInstallment $installment,
        PushNextInstallmentAction $action,
    ): RedirectResponse {
        $this->authorize('splitInstallment', $charge);

        if ((int) $installment->finance_charge_id !== (int) $charge->id) {
            abort(404);
        }

        try {
            $action->handle($charge->id, $installment->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['retry' => 'Push DNG thất bại: '.$e->getMessage()]);
        }

        Inertia::flash('success', "Đợt {$installment->installment_no} đã được push lại sang DNG.");

        return back();
    }

    /**
     * Show the form for creating a new charge.
     */
    public function create(Request $request): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        $chargeTypes = collect(self::MANUAL_CREATE_CHARGE_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => $this->getManualCreateChargeTypeLabel($type),
        ]);

        $student = null;

        if ($request->has('student_id')) {
            $student = Student::find($request->get('student_id'));
        }

        return Inertia::render('Finance/Charges/Create', [
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'student' => $student,
        ]);
    }

    private function getManualCreateChargeTypeLabel(string $type): string
    {
        $dngLabels = collect(DngFeeTypeOptions::all())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']]);

        $dngCode = self::MANUAL_CREATE_CHARGE_TYPE_DNG_LABELS[$type] ?? null;

        if ($dngCode && $dngLabels->has($dngCode)) {
            return $dngLabels->get($dngCode);
        }

        return match ($type) {
            FinanceCharge::TYPE_DEFER_CREDIT => 'Lệ phí bảo lưu',
            FinanceCharge::TYPE_EGC_EXEMPT_CREDIT => 'GC: Miễn trừ phí egc',
            FinanceCharge::TYPE_ADJUSTMENT => 'Điều chỉnh',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Store a newly created charge via Finance Intake (wave 7 materializer-only).
     */
    public function store(StoreFinanceChargeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->createStaffDebitAction->handle($validated);

            Inertia::flash('success', 'Charge created successfully.');

            return redirect()->route('finance.charges.show', $result->finance_charge_id);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the description of a charge.
     */
    public function updateDescription(Request $request, FinanceCharge $charge)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
        ]);

        $charge->update(['description' => $validated['description']]);

        return back()->with('success', 'Mô tả đã được cập nhật.');
    }

    /**
     * Void a charge.
     */
    public function void(Request $request, FinanceCharge $charge)
    {
        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        try {
            $result = $this->voidChargeAction->handle($charge->id, $validated['void_reason']);

            $message = 'Charge voided successfully.';
            if ($result['released_allocations'] > 0) {
                $message .= " Released {$result['released_allocations']} allocations"
                    .' ('.number_format($result['released_amount']).'đ).'
                    .' '.count($result['affected_payments']).' payment(s) now have unapplied balance.';
            }

            if (($result['reallocated_allocations'] ?? 0) > 0) {
                $message .= ' Auto-reallocated '
                    .$result['reallocated_allocations'].' allocation(s)'
                    .' ('.number_format($result['reallocated_amount']).'đ) to remaining unpaid charges.';
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
