<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Finance\Actions\DisposePaymentSurplusAction;
use App\Modules\Finance\Http\Requests\Student360\DisposePaymentSurplusRequest;
use App\Modules\Finance\Http\Requests\Student360\RecordManualPaymentRequest;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Student360\PreviewManualAllocationQuery;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FinanceStudentPaymentController extends Controller
{
    /** JSON: read-only manual-allocate preview for a single payment. */
    public function allocatePreview(Payment $payment, PreviewManualAllocationQuery $query): JsonResponse
    {
        $this->assertCampusVisible((int) $payment->student_id);

        return ApiResponse::success($query->handle($payment));
    }

    public function dispose(Payment $payment, DisposePaymentSurplusRequest $request, DisposePaymentSurplusAction $action): RedirectResponse
    {
        $this->assertCampusVisible((int) $payment->student_id);
        $action->run($payment, $request->validated(), (int) $request->user()->id);

        Inertia::flash('success', 'Đã ghi nhận xử lý khoản Còn dư.');

        return back();
    }

    /** Thin adapter: records a manual payment via the existing PaymentService. */
    public function store(Student $student, RecordManualPaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $this->assertCampusVisible((int) $student->id);

        $data = $request->validated();
        $payments->recordPayment([
            'student_id' => (int) $student->id,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'source' => 'manual',
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'received_by_user_id' => (int) $request->user()->id,
        ]);

        Inertia::flash('success', 'Đã ghi nhận thanh toán.');

        return back();
    }

    protected function assertCampusVisible(int $studentId): void
    {
        $campusId = app()->bound('campus') ? (app('campus')?->id !== null ? (int) app('campus')->id : null) : null;
        $owner = Student::find($studentId)?->campus_id;

        $visible = $campusId !== null && $owner !== null && (int) $owner === $campusId;
        if (! $visible && ! (request()->user()?->can('view_finance_all_campus') ?? false)) {
            abort(404);
        }
    }
}
