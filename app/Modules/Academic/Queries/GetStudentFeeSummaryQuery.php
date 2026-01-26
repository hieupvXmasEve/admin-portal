<?php

namespace App\Modules\Academic\Queries;

use App\Models\FinanceCharge;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetStudentFeeSummaryQuery
{
    public function execute(Student $student): array
    {
        // --- PREPARE DATA ---

        // 1. Get Tuition Plan (Major Tuition Check)
        $tuitionPlan = TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->where('is_active', true)
            ->with(['terms', 'curriculumVersion.program'])
            ->first();

        // 2. Get All Invoices (Actual Billing)
        $invoices = \App\Models\StudentInvoice::where('student_id', $student->id)
            ->with(['semester', 'invoiceLines.charge', 'invoiceLines.charge.allocations.payment'])
            ->orderBy('due_date')
            ->get();

        // 3. Get All Charges (For finding links to Plan Terms)
        $charges = FinanceCharge::where('student_id', $student->id)
            ->with(['allocations'])
            ->get();

        // --- SECTION A: BILLING BY SEMESTER (ACTUAL) ---
        
        // Group invoices by semester
        $groupedInvoices = $invoices->groupBy('semester_id');
        
        // We want to list all semesters that have invoices, sorted by start date
        // Since invoices are loaded with semester, we can extract unique semesters from them
        $semestersWithInvoices = $invoices->pluck('semester')->unique('id')->sortBy('start_date');

        $billingBySemester = $semestersWithInvoices->map(function ($semester) use ($groupedInvoices) {
            $semInvoices = $groupedInvoices->get($semester->id) ?? collect();
            
            $total = $semInvoices->sum('total_amount');
            $paid = $semInvoices->sum('paid_amount');
            $remaining = $total - $paid;

            // Calculate semester status based on invoices
            $status = 'paid';
            if ($semInvoices->isEmpty()) {
                $status = 'no_invoices';
            } elseif ($remaining > 0) {
                // Check if any invoice is overdue
                $hasOverdue = $semInvoices->contains(fn($inv) => $inv->due_date && $inv->due_date < now() && ($inv->total_amount - $inv->paid_amount) > 0);
                $status = $hasOverdue ? 'overdue' : ($paid > 0 ? 'partial' : 'unpaid');
            }

            return [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'semester_code' => $semester->code,
                'status' => $status,
                'totals' => [
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                ],
                'invoices' => $semInvoices->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'created_at' => $inv->created_at->format('Y-m-d'),
                        'due_date' => $inv->due_date?->format('Y-m-d'),
                        'status' => $inv->status, // Use invoice status from DB or derive if needed
                        'total' => $inv->total_amount,
                        'paid' => $inv->paid_amount,
                        'remaining' => $inv->total_amount - $inv->paid_amount,
                        'lines' => $inv->invoiceLines->map(function ($line) {
                            $charge = $line->charge;
                            return [
                                'id' => $line->id,
                                'item' => $charge->description ?? $charge->charge_type,
                                'category' => $this->getChargeCategory($charge->charge_type),
                                'type' => $charge->amount < 0 ? 'Discount' : 'Charge',
                                'amount' => $charge->amount,
                            ];
                        }),
                        'payments' => $inv->invoiceLines->flatMap(fn($l) => $l->charge->allocations)->map(fn($a) => $a->payment)->unique('id')->map(fn($p) => [
                            'id' => $p->id,
                            'paid_at' => $p->paid_at?->format('Y-m-d'),
                            'method' => $p->method,
                            'amount' => $p->amount,
                            'ref' => $p->external_ref,
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        // --- SECTION B: TUITION PLAN CHECKLIST ---

        $checklist = null;
        if ($tuitionPlan) {
            // Get semesters sequence for inferring term semesters
            $intakeSemester = \App\Models\Semester::find($student->intake_semester_id);
            $semestersSequence = collect();
            if ($intakeSemester) {
                // Get enough semesters forward
                $semestersSequence = \App\Models\Semester::where('start_date', '>=', $intakeSemester->start_date)
                    ->orderBy('start_date')
                    ->limit(20) // Limit to reasonable amount
                    ->get();
            }

            $terms = $tuitionPlan->terms->map(function ($term) use ($charges, $invoices, $semestersSequence) {
                // Inferred semester for this term
                $inferredSemester = $semestersSequence->get($term->term_number - 1);

                // Find charge linked to this term
                // Priority 1: Strict match by source (if populated)
                $linkedCharge = $charges->first(function ($c) use ($term) {
                    return $c->source_type === TuitionPlanTerm::class && $c->source_id === $term->id;
                });

                // Priority 2: Fallback match by Type + Semester (for legacy/imported data)
                if (!$linkedCharge && $inferredSemester) {
                    $linkedCharge = $charges->first(function ($c) use ($term, $inferredSemester) {
                        return $c->charge_type === FinanceCharge::TYPE_TUITION_TERM 
                            && $c->semester_id === $inferredSemester->id;
                    });
                }

                $generated = (bool)$linkedCharge;
                $paymentStatus = 'not_generated';
                $linkedInvoiceRefs = [];

                if ($linkedCharge) {
                    $paid = $linkedCharge->paid_amount;
                    $amount = $linkedCharge->amount;
                    $remaining = $amount - $paid;

                    // Find invoices containing this charge
                    // We can use the already loaded invoices to find where this charge appears
                    // Invoice -> InvoiceLines -> Charge
                    $linkedInvoiceObjs = collect();
                    foreach ($invoices as $inv) {
                        if ($inv->invoiceLines->contains('charge_id', $linkedCharge->id)) {
                            $linkedInvoiceRefs[] = $inv->invoice_number;
                            $linkedInvoiceObjs->push($inv);
                        }
                    }

                    // Determine Status
                    // Priority 1: If charge is fully allocated -> Paid
                    if ($remaining <= 0) {
                        $paymentStatus = 'paid';
                    } 
                    // Priority 2: If any linked invoice is marked 'paid' (covers discounts case) -> Paid
                    elseif ($linkedInvoiceObjs->contains('status', 'paid')) {
                        $paymentStatus = 'paid';
                    }
                    // Priority 3: Partial allocation
                    elseif ($paid > 0) {
                        $paymentStatus = 'partial';
                    } 
                    // Priority 4: Default
                    else {
                        $paymentStatus = 'unpaid';
                    }
                }

                return [
                    'term_number' => $term->term_number,
                    'semester_name' => $inferredSemester ? $inferredSemester->name : "Term " . $term->term_number,
                    'required_amount' => $term->amount,
                    'generated' => $generated,
                    'charge_id' => $linkedCharge?->id,
                    'payment_status' => $paymentStatus,
                    'linked_invoices' => $linkedInvoiceRefs,
                ];
            });

            $checklist = [
                'plan_name' => $tuitionPlan->curriculumVersion->program->name . ' (' . $tuitionPlan->curriculumVersion->version_code . ')',
                'terms' => $terms,
            ];
        }

        // --- OVERALL SUMMARY ---
        // Using charges for accurate totals
        $totalCharged = $charges->where('amount', '>', 0)->sum('amount');
        $totalDiscount = $charges->where('amount', '<', 0)->sum('amount'); // This is negative
        $totalPaid = $charges->sum(fn($c) => $c->allocations->sum('allocated_amount'));
        
        // Remaining is roughly (Charged - abs(Discount)) - Paid
        // Since discount is negative, Charged + Discount = Net Due.
        $netDue = $totalCharged + $totalDiscount;
        $remaining = $netDue - $totalPaid;

        return [
            'student_info' => [
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'program' => $student->program?->name,
                'intake' => $student->intakeSemester?->name,
            ],
            'summary' => [
                'total_charged' => $totalCharged,
                'total_discount' => $totalDiscount,
                'total_paid' => $totalPaid,
                'remaining' => $remaining,
                'progress' => $netDue > 0 ? min(100, max(0, ($totalPaid / $netDue) * 100)) : 0,
            ],
            'billing_by_semester' => $billingBySemester,
            'tuition_plan_checklist' => $checklist,
        ];
    }

    private function getChargeCategory(string $type): string
    {
        return match ($type) {
            FinanceCharge::TYPE_TUITION_TERM => 'Tuition',
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'EGC',
            FinanceCharge::TYPE_SCHOLARSHIP_CREDIT, FinanceCharge::TYPE_VOUCHER_CREDIT => 'Discount',
            FinanceCharge::TYPE_RETAKE_FEE => 'Retake',
            default => 'Other',
        };
    }
}