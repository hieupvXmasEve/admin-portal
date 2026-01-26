<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Semester;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinanceController extends Controller
{
    /**
     * Get finance summary (semesters and global balance).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            // 1. Get global unapplied credit from COMPLETED payments
            $globalUnapplied = Payment::query()
                ->completed()
                ->forStudent($student->id)
                ->get()
                ->sum('unapplied_amount');

            // 2. Get all charges grouped by semester
            $charges = FinanceCharge::query()
                ->forStudent($student->id)
                ->with(['semester:id,code,name,start_date', 'allocations'])
                ->get();

            // Group by semester_id
            $grouped = $charges->groupBy('semester_id');

            $semestersData = [];

            foreach ($grouped as $semesterId => $semesterCharges) {
                // Skip if semester relation is missing (orphan charges)
                $semester = $semesterCharges->first()->semester;
                if (! $semester) {
                    continue;
                }

                $totalDue = $semesterCharges->sum('amount');
                $totalPaid = $semesterCharges->sum('paid_amount'); // Accessor uses allocations sum
                $balance = $totalDue - $totalPaid;

                // Determine status
                $status = 'unpaid';
                if ($totalDue <= 0 && $totalPaid == 0) {
                    // No effective due and no payment -> No Fee / Balanced
                    $status = 'no_fee';
                } else {
                    $diff = $totalDue - $totalPaid;
                    // Floating point comparison tolerance could be added if needed,
                    // but using simple logic for now.
                    if (abs($diff) < 0.01) {
                        $status = 'paid';
                    } elseif ($diff < 0) {
                        $status = 'overpaid';
                    } elseif ($totalPaid > 0) {
                        $status = 'partial';
                    } else {
                        $status = 'unpaid';
                    }
                }

                // Badges logic
                $badges = [];
                $chargeTypes = $semesterCharges->pluck('charge_type')->unique();
                if ($chargeTypes->contains(FinanceCharge::TYPE_EGC_LEVEL_FEE)) {
                    $badges[] = 'EGC';
                }
                if ($chargeTypes->contains(FinanceCharge::TYPE_RETAKE_FEE)) {
                    $badges[] = 'Retake';
                }
                if ($chargeTypes->contains(FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)) {
                    $badges[] = 'Scholarship';
                }
                if ($chargeTypes->contains(FinanceCharge::TYPE_VOUCHER_CREDIT)) {
                    $badges[] = 'Voucher';
                }
                if ($chargeTypes->contains(FinanceCharge::TYPE_DEFER_CREDIT)) {
                    $badges[] = 'Defer';
                }
                // Major check: standard tuition
                if ($chargeTypes->contains(FinanceCharge::TYPE_TUITION_TERM)) {
                    $badges[] = 'Major';
                }

                $semestersData[] = [
                    'semester_id' => $semester->id,
                    'semester_code' => $semester->code,
                    'semester_name' => $semester->name,
                    'start_date' => $semester->start_date,
                    'due_amount' => (float) $totalDue,
                    'paid_amount' => (float) $totalPaid,
                    'balance' => (float) $balance,
                    'status' => $status,
                    'badges' => array_values(array_unique($badges)),
                ];
            }

            $semestersData = collect($semestersData)
                ->sortByDesc('start_date')
                ->values()
                ->all();

            return ApiResponse::success([
                'global_balance' => (float) $globalUnapplied,
                'semesters' => $semestersData,
            ]);

        } catch (\Exception $e) {
            Log::error('Finance index error', [
                'error' => $e->getMessage(),
                'student_id' => $request->user()->id,
            ]);

            return ApiResponse::serverError('Failed to retrieve finance summary');
        }
    }

    /**
     * Get finance details for a specific semester.
     */
    public function semester(Request $request, int $semesterId): JsonResponse
    {
        try {
            $student = $request->user();

            $semester = Semester::find($semesterId);
            if (! $semester) {
                return ApiResponse::notFound('Semester not found');
            }

            // Get charges details
            $charges = FinanceCharge::query()
                ->forStudent($student->id)
                ->forSemester($semesterId)
                ->with(['source', 'allocations.payment']) // Load allocations and payment
                ->orderBy('effective_at')
                ->get();

            // Transform Ledger Lines
            $lines = $charges->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'type' => $charge->charge_type,
                    'amount' => (float) $charge->amount,
                    'description' => $charge->description,
                    'effective_at' => $charge->effective_at,
                    'voided_at' => $charge->voided_at,
                    'is_void' => $charge->status === FinanceCharge::STATUS_VOID,
                    'paid_amount' => $charge->paid_amount,
                ];
            });

            // Payment History
            $payments = collect();
            foreach ($charges as $charge) {
                foreach ($charge->allocations as $allocation) {
                    $payment = $allocation->payment;
                    if ($payment) {
                        $payments->push([
                            'payment_id' => $payment->id,
                            'payment_date' => $payment->paid_at,
                            'method' => $payment->method,
                            'total_payment_amount' => (float) $payment->amount, // Original payment
                            'allocated_to_this_semester' => (float) $allocation->allocated_amount,
                        ]);
                    }
                }
            }

            // Group payments by payment_id
            $groupedPayments = $payments->groupBy('payment_id')->map(function ($items) {
                $first = $items->first();

                return [
                    'id' => $first['payment_id'],
                    'date' => $first['payment_date'],
                    'method' => $first['method'],
                    'amount' => $items->sum('allocated_to_this_semester'),
                    'original_total' => $first['total_payment_amount'],
                ];
            })->values();

            // Summary for this semester
            $totalDue = $lines->sum('amount');
            $totalPaid = $groupedPayments->sum('amount');
            $balance = $totalDue - $totalPaid;

            return ApiResponse::success([
                'semester' => [
                    'id' => $semester->id,
                    'code' => $semester->code,
                    'name' => $semester->name,
                ],
                'charges' => $lines,
                'payments' => $groupedPayments,
                'summary' => [
                    'due_amount' => (float) $totalDue,
                    'paid_amount' => (float) $totalPaid,
                    'balance' => (float) $balance,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Finance semester error', [
                'error' => $e->getMessage(),
                'student_id' => $request->user()->id,
            ]);

            return ApiResponse::serverError('Failed to retrieve finance details');
        }
    }
}
