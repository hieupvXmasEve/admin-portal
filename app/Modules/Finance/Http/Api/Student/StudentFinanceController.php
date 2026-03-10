<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFinanceController extends Controller
{
    public function __construct(
        private FinanceChargeService $chargeService,
        private PaymentService $paymentService
    ) {}

    /**
     * Get student balance summary.
     */
    public function balance(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = $validated['semester_id'] ?? null;
        $balance = $this->paymentService->getStudentBalance($student->id, $semesterId);

        return ApiResponse::success([
            'balance' => $balance,
            'semester_id' => $semesterId,
        ]);
    }

    /**
     * Get student charges.
     */
    public function charges(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = $validated['semester_id'] ?? null;
        $charges = $this->chargeService->getStudentCharges($student->id, $semesterId);

        // Add computed attributes
        $charges->each(function ($charge) {
            $charge->append(['is_charge', 'is_credit', 'paid_amount', 'balance', 'is_fully_paid']);
        });

        return ApiResponse::success([
            'charges' => $charges,
            'summary' => [
                'total_charges' => $this->chargeService->getTotalCharges($student->id, $semesterId),
                'total_credits' => $this->chargeService->getTotalCredits($student->id, $semesterId),
                'net_amount' => $this->chargeService->getNetAmount($student->id, $semesterId),
            ],
        ]);
    }

    /**
     * Get student payment history.
     */
    public function payments(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $payments = $this->paymentService->getPaymentHistory($student->id);

        // Add computed attributes
        $payments->each(function ($payment) {
            $payment->append(['allocated_amount', 'unapplied_amount', 'is_fully_allocated']);
        });

        return ApiResponse::success([
            'payments' => $payments,
            'unapplied_credit' => $this->paymentService->getUnappliedCredits($student->id),
        ]);
    }

    /**
     * Get specific charge details.
     */
    public function chargeDetail(Request $request, int $chargeId): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $charge = \App\Models\FinanceCharge::where('id', $chargeId)
            ->where('student_id', $student->id)
            ->with(['semester', 'allocations.payment'])
            ->first();

        if (! $charge) {
            return ApiResponse::error('Charge not found', [], 404);
        }

        $charge->append(['is_charge', 'is_credit', 'paid_amount', 'balance', 'is_fully_paid']);

        return ApiResponse::success([
            'charge' => $charge,
        ]);
    }
}
