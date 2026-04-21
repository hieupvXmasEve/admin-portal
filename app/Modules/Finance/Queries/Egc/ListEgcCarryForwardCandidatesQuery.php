<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Modules\Finance\Actions\Egc\BuildEgcCarryForwardPlanAction;

class ListEgcCarryForwardCandidatesQuery
{
    public function __construct(
        private BuildEgcCarryForwardPlanAction $buildPlanAction,
    ) {}

    public function handle(int $semesterId, ?int $campusId = null): array
    {
        $students = Student::query()
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->whereHas('financeCharges', function ($query) use ($semesterId) {
                $query->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                    ->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->where('semester_id', $semesterId)
                    ->where('amount', '>', 0);
            })
            ->with([
                'egcProgress.semester:id,name',
                'financeCharges' => fn ($query) => $query
                    ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                    ->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->with([
                        'semester:id,name',
                        'invoiceLines.invoice:id,invoice_number,student_id,semester_id',
                        'invoiceLines.paymentApplications',
                        'invoiceLines.discountAllocations',
                    ]),
                'payments' => fn ($query) => $query
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->with('applications'),
                'courseRegistrations' => fn ($query) => $query
                    ->where('semester_id', $semesterId)
                    ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn'])
                    ->whereHas('courseOffering.unit', fn ($unitQuery) => $unitQuery->where('unit_type', 'egc'))
                    ->with('courseOffering:id,semester_id')
                    ->orderBy('registration_date')
                    ->orderBy('id'),
            ])
            ->orderBy('student_id')
            ->get();

        $groups = [
            'eligible' => [],
            'needs_data_repair' => [],
            'ineligible' => [],
        ];

        foreach ($students as $student) {
            $candidate = $this->buildPlanAction->run($student, $semesterId);
            $groups[$candidate['status']][] = $candidate;
        }

        return $groups;
    }
}
