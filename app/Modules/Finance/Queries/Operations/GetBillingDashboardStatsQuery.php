<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetBillingDashboardStatsQuery
{
    public function handle(?int $semesterId): array
    {
        $campusId = app('campus')?->id;
        
        if (!$semesterId) {
             return [
                'eligible_count' => 0,
                'charged_count' => 0,
                'uncharged_count' => 0,
                'paid_count' => 0,
                'unpaid_count' => 0,
                'partial_paid_count' => 0,
                'total_charges' => 0,
                'total_credits' => 0,
                'total_paid' => 0,
                'total_balance' => 0,
                'defer_preserve_count' => 0,
                'defer_forfeit_count' => 0,
                'retake_unpaid_count' => 0,
            ];
        }

        // 1. Define Eligible Students Scope
        $eligibleQuery = Student::query()
            ->where('intake_semester_id', '<=', $semesterId)
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->where(function (Builder $q) use ($semesterId) {
                $q->whereHas('courseRegistrations', fn($sq) => $sq->where('semester_id', $semesterId));
                $q->orWhereHas('deferCases', fn($sq) => $sq->where('semester_id', $semesterId));
                $q->orWhereHas('financeCharges', fn($sq) => 
                    $sq->where('semester_id', $semesterId)->where('status', FinanceCharge::STATUS_ACTIVE)
                );
                $q->orWhereHas('invoices', fn($sq) => $sq->where('semester_id', $semesterId));
            });

        $eligibleCount = $eligibleQuery->count();
        
        // 2. Charged / Uncharged
        $chargedCount = (clone $eligibleQuery)->where(function ($q) use ($semesterId) {
            $q->whereHas('financeCharges', fn($sq) => 
                $sq->where('semester_id', $semesterId)->where('status', FinanceCharge::STATUS_ACTIVE)
            )->orWhereHas('invoices', fn($sq) => $sq->where('semester_id', $semesterId));
        })->count();

        // 3. Financial Totals (Ledger based)
        $chargesBase = FinanceCharge::query()
            ->active()
            ->where('semester_id', $semesterId)
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)));
        
        $totalCharges = (float) (clone $chargesBase)->charges()->sum('amount');
        $totalCredits = (float) (clone $chargesBase)->credits()->sum(DB::raw('ABS(amount)'));
        
        $totalPaid = (float) DB::table('payment_allocations')
            ->join('finance_charges', 'payment_allocations.charge_id', '=', 'finance_charges.id')
            ->join('students', 'finance_charges.student_id', '=', 'students.id')
            ->where('finance_charges.semester_id', $semesterId)
            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
            ->when($campusId, fn($q) => $q->where('students.campus_id', $campusId))
            ->sum('payment_allocations.allocated_amount');

        $totalBalance = $totalCharges - $totalCredits - $totalPaid;

        // 4. Paid / Unpaid / Partial Counts
        $studentBalances = DB::table('finance_charges')
            ->select('finance_charges.student_id')
            ->selectRaw('SUM(amount) as net_due')
            ->selectRaw('COALESCE(SUM(pa.allocated), 0) as paid')
            ->leftJoinSub(
                DB::table('payment_allocations')
                    ->select('charge_id', DB::raw('SUM(allocated_amount) as allocated'))
                    ->groupBy('charge_id'),
                'pa',
                'finance_charges.id',
                '=',
                'pa.charge_id'
            )
            ->join('students', 'finance_charges.student_id', '=', 'students.id')
            ->where('finance_charges.semester_id', $semesterId)
            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
            ->when($campusId, fn($q) => $q->where('students.campus_id', $campusId))
            ->groupBy('finance_charges.student_id')
            ->get();
            
        $paidCount = 0;
        $partialCount = 0;
        $unpaidCount = 0;
        
        foreach ($studentBalances as $s) {
            $netDue = (float)$s->net_due;
            $paid = (float)$s->paid;
            $balance = $netDue - $paid;
            
            if ($balance <= 0) {
                 $paidCount++;
            } elseif ($paid > 0) {
                 $partialCount++;
            } else {
                 $unpaidCount++;
            }
        }

        // 5. Defer Statistics
        $deferPreserveCount = DeferCase::query()
            ->where('semester_id', $semesterId)
            ->where('fee_policy', DeferCase::POLICY_PRESERVE)
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)))
            ->count();
            
        $deferForfeitCount = DeferCase::query()
            ->where('semester_id', $semesterId)
            ->where('fee_policy', DeferCase::POLICY_FORFEIT)
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)))
            ->count();
            
        // 6. Retake Unpaid
        $retakeUnpaidCount = Student::query()
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->whereHas('courseRegistrations', fn($q) => 
                $q->where('semester_id', $semesterId)->where('is_retake', true)
            )
            ->where(function ($q) use ($semesterId) {
                $q->whereDoesntHave('financeCharges', fn($sq) => 
                    $sq->where('semester_id', $semesterId)
                       ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                       ->active()
                );
                $q->orWhereHas('financeCharges', function ($sq) use ($semesterId) {
                    $sq->where('semester_id', $semesterId)
                       ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                       ->active()
                       ->whereRaw('(amount - (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE charge_id = finance_charges.id)) > 0');
                });
            })
            ->count();

        return [
            'eligible_count' => $eligibleCount,
            'charged_count' => $chargedCount,
            'uncharged_count' => max(0, $eligibleCount - $chargedCount),
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'partial_paid_count' => $partialCount,
            'total_charges' => $totalCharges,
            'total_credits' => $totalCredits,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
            'defer_preserve_count' => $deferPreserveCount,
            'defer_forfeit_count' => $deferForfeitCount,
            'retake_unpaid_count' => $retakeUnpaidCount,
        ];
    }
}
