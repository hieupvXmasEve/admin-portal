<?php

namespace App\Modules\Academic\Queries;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;

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

        // 3. Get All Active Charges (exclude voided)
        $charges = FinanceCharge::where('student_id', $student->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->with(['allocations.payment', 'semester'])
            ->get();

        // 4. Get completed payments with allocation trail
        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->with([
                'allocations.charge.semester',
                'allocations.charge.invoiceLines.invoice',
            ])
            ->orderByDesc('paid_at')
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
                $hasOverdue = $semInvoices->contains(fn ($inv) => $inv->due_date && $inv->due_date < now() && ($inv->total_amount - $inv->paid_amount) > 0);
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
                    $invoicePayments = $inv->invoiceLines
                        ->flatMap(function ($line) use ($inv) {
                            if (! $line->charge) {
                                return collect();
                            }

                            return $line->charge->allocations
                                ->filter(fn ($allocation) => $allocation->payment !== null)
                                ->map(function ($allocation) use ($line, $inv) {
                                    return [
                                        'payment_id' => $allocation->payment_id,
                                        'paid_at' => $allocation->payment->paid_at?->format('Y-m-d'),
                                        'method' => $allocation->payment->method,
                                        'source' => $allocation->payment->source,
                                        'payment_amount' => (float) $allocation->payment->amount,
                                        'allocated_amount' => (float) $allocation->allocated_amount,
                                        'ref' => $allocation->payment->external_ref,
                                        'charge_id' => $line->charge->id,
                                        'charge_description' => $line->charge->description ?? $line->description_snapshot,
                                        'invoice_id' => $inv->id,
                                    ];
                                });
                        })
                        ->groupBy('payment_id')
                        ->map(function ($paymentAllocations) {
                            $firstAllocation = $paymentAllocations->first();

                            return [
                                'id' => $firstAllocation['payment_id'],
                                'paid_at' => $firstAllocation['paid_at'],
                                'method' => $firstAllocation['method'],
                                'source' => $firstAllocation['source'],
                                'amount' => $firstAllocation['payment_amount'],
                                'allocated_amount' => (float) $paymentAllocations->sum('allocated_amount'),
                                'ref' => $firstAllocation['ref'],
                                'charges' => $paymentAllocations
                                    ->groupBy('charge_id')
                                    ->map(function ($chargeAllocations) {
                                        $firstCharge = $chargeAllocations->first();

                                        return [
                                            'charge_id' => $firstCharge['charge_id'],
                                            'charge_description' => $firstCharge['charge_description'],
                                            'allocated_amount' => (float) $chargeAllocations->sum('allocated_amount'),
                                        ];
                                    })
                                    ->values(),
                            ];
                        })
                        ->values();

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
                        'payments' => $invoicePayments,
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

            // Pre-fetch student's scholarship for expected discount on future terms
            $scholarshipAward = StudentScholarshipAward::where('student_id', $student->id)
                ->with('scholarshipDefinition')
                ->first();

            $terms = $tuitionPlan->terms->map(function ($term) use ($charges, $invoices, $semestersSequence, $scholarshipAward) {
                // Inferred semester for this term
                $inferredSemester = $semestersSequence->get($term->term_number - 1);

                // Find charge linked to this term
                // Priority 1: Strict match by source (if populated)
                $linkedCharge = $charges->first(function ($c) use ($term) {
                    return $c->source_type === TuitionPlanTerm::class && $c->source_id === $term->id;
                });

                // Priority 2: Fallback match by Type + Semester (for legacy/imported data)
                if (! $linkedCharge && $inferredSemester) {
                    $linkedCharge = $charges->first(function ($c) use ($inferredSemester) {
                        return $c->charge_type === FinanceCharge::TYPE_TUITION_TERM
                            && $c->semester_id === $inferredSemester->id;
                    });
                }

                $generated = (bool) $linkedCharge;
                $paymentStatus = 'not_generated';
                $linkedInvoiceDetails = [];

                // Calculate discount/scholarship for this semester
                $discountAmount = 0;
                $isEstimatedDiscount = false;
                if ($inferredSemester) {
                    $discountAmount = abs($charges->filter(function ($c) use ($inferredSemester) {
                        return $c->semester_id === $inferredSemester->id && $c->amount < 0;
                    })->sum('amount'));
                }

                // For terms without existing discount charges, estimate from scholarship award
                if ($discountAmount == 0 && $scholarshipAward && $scholarshipAward->scholarshipDefinition) {
                    $scholarshipDef = $scholarshipAward->scholarshipDefinition;
                    if ($scholarshipDef->type === 'percentage') {
                        $discountAmount = ($term->amount * $scholarshipDef->amount) / 100;
                    } else {
                        $discountAmount = (float) $scholarshipDef->amount;
                    }
                    $isEstimatedDiscount = $discountAmount > 0;
                }

                $amountDue = max(0, $term->amount - $discountAmount);
                $paidAmount = 0;

                if ($linkedCharge) {
                    $paidAmount = $linkedCharge->paid_amount;
                    $amount = $linkedCharge->amount;
                    $remaining = $amount - $paidAmount;

                    // Find invoices containing this charge with full details
                    $linkedInvoiceObjs = collect();
                    foreach ($invoices as $inv) {
                        if ($inv->invoiceLines->contains('charge_id', $linkedCharge->id)) {
                            $linkedInvoiceDetails[] = [
                                'id' => $inv->id,
                                'invoice_number' => $inv->invoice_number,
                                'status' => $inv->status,
                                'due_date' => $inv->due_date?->toDateString(),
                            ];
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
                    elseif ($paidAmount > 0) {
                        $paymentStatus = 'partial';
                    }
                    // Priority 4: Default
                    else {
                        $paymentStatus = 'unpaid';
                    }
                }

                return [
                    'term_number' => $term->term_number,
                    'semester_id' => $inferredSemester?->id,
                    'semester_name' => $inferredSemester ? $inferredSemester->name : 'Term '.$term->term_number,
                    'required_amount' => $term->amount,
                    'discount_amount' => $discountAmount,
                    'is_estimated_discount' => $isEstimatedDiscount,
                    'amount_due' => $amountDue,
                    'paid_amount' => $paidAmount,
                    'generated' => $generated,
                    'charge_id' => $linkedCharge?->id,
                    'payment_status' => $paymentStatus,
                    'invoices' => $linkedInvoiceDetails,
                ];
            });

            $checklist = [
                'plan_name' => $tuitionPlan->curriculumVersion->program->name.' ('.$tuitionPlan->curriculumVersion->version_code.')',
                'terms' => $terms,
            ];
        }

        // --- OVERALL SUMMARY ---
        $totalCharged = (float) $charges->where('amount', '>', 0)->sum('amount');
        $totalDiscount = (float) $charges->where('amount', '<', 0)->sum('amount');
        $totalAllocated = (float) $charges->sum(fn ($c) => $c->allocations->sum('allocated_amount'));

        // Net due = charges - discounts
        $netDue = $totalCharged + $totalDiscount;
        $outstanding = max(0, $netDue - $totalAllocated);
        $totalPayments = (float) $payments->sum('amount');
        $totalUnapplied = (float) $payments->sum(fn ($p) => $p->unapplied_amount);
        $paymentHistory = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'method' => $payment->method,
                'source' => $payment->source,
                'amount' => (float) $payment->amount,
                'allocated_amount' => (float) $payment->allocated_amount,
                'unapplied_amount' => (float) $payment->unapplied_amount,
                'ref' => $payment->external_ref,
                'allocations' => $payment->allocations->map(function ($allocation) {
                    $charge = $allocation->charge;
                    $invoice = $charge?->invoiceLines
                        ->first(fn ($line) => $line->invoice !== null)?->invoice;

                    return [
                        'allocation_id' => $allocation->id,
                        'allocated_amount' => (float) $allocation->allocated_amount,
                        'semester_name' => $charge?->semester?->name,
                        'invoice_id' => $invoice?->id,
                        'invoice_number' => $invoice?->invoice_number,
                        'charge_id' => $charge?->id,
                        'charge_description' => $charge?->description ?? $charge?->charge_type,
                    ];
                })->values(),
            ];
        })->values();
        $statementEvents = $payments
            ->flatMap(function ($payment) {
                $paymentEvent = collect([
                    [
                        'event_key' => "payment-{$payment->id}",
                        'event_at' => $payment->paid_at,
                        'sort_order' => 0,
                        'kind' => 'payment',
                        'label' => 'Payment received',
                        'reference' => "Payment #{$payment->id}",
                        'details' => collect([
                            ucfirst(str_replace('_', ' ', $payment->method)),
                            $payment->source ? 'Source: '.$payment->source : null,
                            $payment->external_ref ? 'Ref: '.$payment->external_ref : null,
                        ])->filter()->implode(' • '),
                        'money_in' => (float) $payment->amount,
                        'money_out' => 0.0,
                    ],
                ]);

                $allocationEvents = $payment->allocations->map(function ($allocation) {
                    $charge = $allocation->charge;
                    $invoice = $charge?->invoiceLines
                        ->first(fn ($line) => $line->invoice !== null)?->invoice;

                    return [
                        'event_key' => "allocation-{$allocation->id}",
                        'event_at' => $allocation->allocated_at ?? $allocation->created_at,
                        'sort_order' => 1,
                        'kind' => 'allocation',
                        'label' => 'Allocated to invoice',
                        'reference' => $invoice?->invoice_number ?? 'Unlinked allocation',
                        'details' => collect([
                            $charge?->semester?->name,
                            $charge?->description ?? $charge?->charge_type,
                        ])->filter()->implode(' • '),
                        'money_in' => 0.0,
                        'money_out' => (float) $allocation->allocated_amount,
                    ];
                });

                return $paymentEvent->concat($allocationEvents);
            })
            ->sort(function (array $left, array $right) {
                $leftAt = $left['event_at']?->getTimestamp() ?? 0;
                $rightAt = $right['event_at']?->getTimestamp() ?? 0;

                if ($leftAt === $rightAt) {
                    return $left['sort_order'] <=> $right['sort_order'];
                }

                return $leftAt <=> $rightAt;
            })
            ->values();

        $runningUnappliedBalance = 0.0;
        $statementEvents = $statementEvents->map(function (array $event) use (&$runningUnappliedBalance) {
            $runningUnappliedBalance += $event['money_in'];
            $runningUnappliedBalance -= $event['money_out'];

            return [
                'event_key' => $event['event_key'],
                'event_at' => $event['event_at']?->format('Y-m-d H:i'),
                'kind' => $event['kind'],
                'label' => $event['label'],
                'reference' => $event['reference'],
                'details' => $event['details'],
                'money_in' => $event['money_in'],
                'money_out' => $event['money_out'],
                'unapplied_balance' => $runningUnappliedBalance,
            ];
        })->values();

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
                'active_due' => $netDue,
                'total_paid' => $totalPayments,
                'total_allocated' => $totalAllocated,
                'outstanding' => $outstanding,
                'unapplied_balance' => $totalUnapplied,
                'remaining' => max(0, $netDue - $totalPayments),
                'net_amount_to_collect' => max(0, $outstanding - $totalUnapplied),
                'payment_count' => $payments->count(),
                'progress' => $netDue > 0 ? min(100, max(0, ($totalAllocated / $netDue) * 100)) : 0,
            ],
            'payments' => $paymentHistory,
            'statement_events' => $statementEvents,
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
