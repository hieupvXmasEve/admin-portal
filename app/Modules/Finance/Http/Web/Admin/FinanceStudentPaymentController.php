<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Actions\DisposePaymentSurplusAction;
use App\Modules\Finance\Actions\RecordAndAllocateManualPaymentAction;
use App\Modules\Finance\Http\Requests\Student360\DisposePaymentSurplusRequest;
use App\Modules\Finance\Http\Requests\Student360\PreviewManualPaymentRequest;
use App\Modules\Finance\Http\Requests\Student360\RecordManualPaymentRequest;
use App\Modules\Finance\Models\FinancePaymentVoucher;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Student360\PreviewManualAllocationQuery;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Inertia\Inertia;

class FinanceStudentPaymentController extends Controller
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    /** JSON: read-only manual-allocate preview for a single payment. */
    public function allocatePreview(Payment $payment, PreviewManualAllocationQuery $query): JsonResponse
    {
        $this->assertCampusVisible((int) $payment->student_id);

        return ApiResponse::success($query->handle($payment));
    }

    public function proposedAllocatePreview(
        int $student,
        PreviewManualPaymentRequest $request,
        PreviewManualAllocationQuery $query,
    ): JsonResponse {
        $studentReference = $this->assertCampusVisible($student);
        abort_if($studentReference === null, 404);

        return ApiResponse::success($query->handleProposed(
            $studentReference->id,
            (float) $request->validated('amount'),
        ));
    }

    public function dispose(Payment $payment, DisposePaymentSurplusRequest $request, DisposePaymentSurplusAction $action): RedirectResponse
    {
        $this->assertCampusVisible((int) $payment->student_id);
        $action->run($payment, $request->validated(), (int) $request->user()->id);

        Inertia::flash('success', 'Đã ghi nhận xử lý khoản Còn dư.');

        return back();
    }

    public function store(int $student, RecordManualPaymentRequest $request, RecordAndAllocateManualPaymentAction $action): RedirectResponse
    {
        $studentReference = $this->assertCampusVisible($student);
        abort_if($studentReference === null, 404);

        $data = $request->validated();
        $result = $action->run([
            'student_id' => $studentReference->id,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $data['idempotency_key'],
            'allocations' => $data['allocations'],
        ], (int) $request->user()->id);

        Inertia::flash('success', 'Đã ghi nhận thanh toán và lập phiếu thu nội bộ.');
        Inertia::flash('payment_voucher_print_url', route('finance.payments.receipt', $result['payment']));

        return back();
    }

    public function receipt(Payment $payment): View
    {
        $this->assertCampusVisible((int) $payment->student_id);
        abort_unless($this->userCanViewReceipt(), 403);

        $voucher = FinancePaymentVoucher::query()
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->firstOrFail();
        $voucher->load(['campus', 'issuedBy']);
        $student = $this->studentReferences->find((int) $payment->student_id);

        return view('print.payment-voucher', [
            'voucher' => $voucher,
            'payment' => $payment,
            'campus' => $voucher->campus ?? Campus::query()->find($voucher->campus_id),
            'studentName' => $student?->fullName ?? '',
            'studentCode' => $student?->studentCode ?? '',
            'issuerName' => $voucher->issuedBy instanceof User ? $voucher->issuedBy->name : '',
            'skipLabels' => [
                FinancePaymentVoucher::SKIP_DNG_HELD => 'Đang bị DNG giữ',
                FinancePaymentVoucher::SKIP_NO_ACTIVE_LINE => 'Không có dòng hóa đơn hoạt động',
                FinancePaymentVoucher::SKIP_CAPPED => 'Vượt số còn phải thu',
                FinancePaymentVoucher::SKIP_STUDENT_MISMATCH => 'Không cùng sinh viên',
            ],
        ]);
    }

    protected function assertCampusVisible(int $studentId): ?StudentReference
    {
        $campusId = app()->bound('campus') ? (app('campus')?->id !== null ? (int) app('campus')->id : null) : null;
        $studentReference = $this->studentReferences->find($studentId);
        $ownerCampusId = $studentReference?->campusId;

        $visible = $campusId !== null && $ownerCampusId !== null && $ownerCampusId === $campusId;
        if (! $visible && ! (request()->user()?->can('view_finance_all_campus') ?? false)) {
            abort(404);
        }

        return $studentReference;
    }

    private function userCanViewReceipt(): bool
    {
        $user = request()->user();

        return (bool) ($user?->can('view_finance_payments') || $user?->can('create_finance_payments'));
    }
}
