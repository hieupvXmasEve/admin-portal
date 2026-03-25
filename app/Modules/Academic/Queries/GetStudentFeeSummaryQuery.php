<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use Illuminate\Support\Collection;

class GetStudentFeeSummaryQuery
{
    public function execute(Student $student): array
    {
        $tuitionPlan = TuitionPlan::query()
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->where('is_active', true)
            ->with(['terms', 'curriculumVersion.program'])
            ->first();

        $invoices = StudentInvoice::query()
            ->where('student_id', $student->id)
            ->with([
                'semester',
                'invoiceLines.charge.semester',
                'invoiceLines.paymentApplications.payment',
                'discounts',
            ])
            ->orderBy('due_date')
            ->get();

        $charges = FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->with(['semester', 'invoiceLines.paymentApplications', 'invoiceLines.invoice'])
            ->get();

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->with([
                'applications.invoiceLine.charge.semester',
                'applications.invoiceLine.invoice',
            ])
            ->orderByDesc('paid_at')
            ->get();

        $billingBySemester = $this->buildBillingBySemester($invoices);
        $checklist = $this->buildTuitionPlanChecklist($student, $tuitionPlan, $charges, $invoices);

        $totalCharged = (float) $invoices->sum(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['subtotal']);
        $totalDiscount = (float) $invoices->sum(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['discount']);
        $totalAllocated = (float) $payments->sum(fn (Payment $payment) => $this->sumNetPaymentApplications($payment->applications));
        $netDue = (float) $invoices->sum(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['total']);
        $outstanding = max(0, $netDue - $totalAllocated);
        $totalPayments = (float) $payments->sum('amount');
        $totalUnapplied = max(0, $totalPayments - $totalAllocated);

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
            'payments' => $this->buildPaymentHistory($payments),
            'statement_events' => $this->buildStatementEvents($payments),
            'billing_by_semester' => $billingBySemester,
            'tuition_plan_checklist' => $checklist,
        ];
    }

    private function buildBillingBySemester(Collection $invoices): Collection
    {
        $groupedInvoices = $invoices->groupBy('semester_id');
        $semesters = $invoices->pluck('semester')->filter()->unique('id')->sortBy('start_date');

        return $semesters->map(function ($semester) use ($groupedInvoices) {
            $semesterInvoices = $groupedInvoices->get($semester->id, collect());

            $totals = $semesterInvoices->reduce(function (array $carry, StudentInvoice $invoice) {
                $snapshot = $this->deriveInvoiceSnapshot($invoice);
                $carry['total'] += $snapshot['total'];
                $carry['paid'] += $snapshot['paid'];

                return $carry;
            }, ['total' => 0.0, 'paid' => 0.0]);

            $remaining = max(0, $totals['total'] - $totals['paid']);

            $status = 'paid';
            if ($semesterInvoices->isEmpty()) {
                $status = 'no_invoices';
            } elseif ($remaining > 0) {
                $hasOverdue = $semesterInvoices->contains(function (StudentInvoice $invoice) {
                    $snapshot = $this->deriveInvoiceSnapshot($invoice);

                    return $invoice->due_date && $invoice->due_date->isPast() && $snapshot['remaining'] > 0;
                });

                $status = $hasOverdue ? 'overdue' : ($totals['paid'] > 0 ? 'partial' : 'unpaid');
            }

            return [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'semester_code' => $semester->code,
                'status' => $status,
                'totals' => [
                    'total' => $totals['total'],
                    'paid' => $totals['paid'],
                    'remaining' => $remaining,
                ],
                'invoices' => $semesterInvoices->map(fn (StudentInvoice $invoice) => $this->mapInvoiceSummary($invoice))->values(),
            ];
        })->values();
    }

    private function mapInvoiceSummary(StudentInvoice $invoice): array
    {
        $snapshot = $this->deriveInvoiceSnapshot($invoice);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'created_at' => $invoice->created_at?->format('Y-m-d') ?? 'N/A',
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'status' => $invoice->status,
            'total' => $snapshot['total'],
            'paid' => $snapshot['paid'],
            'remaining' => $snapshot['remaining'],
            'final_payable' => $snapshot['total'],
            'cash_applied' => $snapshot['paid'],
            'settled_amount' => $snapshot['paid'],
            'snapshot_total' => $snapshot['subtotal'],
            'discount_total' => $snapshot['discount'],
            'lines' => $this->mapInvoiceLines($invoice),
            'payments' => $this->mapInvoicePayments($invoice),
        ];
    }

    private function mapInvoiceLines(StudentInvoice $invoice): Collection
    {
        $chargeLines = $invoice->invoiceLines
            ->filter(function (InvoiceLine $line): bool {
                $chargeAmount = $line->charge?->amount;

                return (float) ($chargeAmount ?? $line->amount_snapshot) > 0;
            })
            ->map(function (InvoiceLine $line): array {
                $charge = $line->charge;

                return [
                    'id' => $line->id,
                    'item' => $charge?->description ?? $line->description_snapshot,
                    'category' => $charge ? $this->getChargeCategory($charge->charge_type) : 'Other',
                    'type' => 'Charge',
                    'amount' => (float) $line->amount_snapshot,
                    'status' => $line->status ?? 'active',
                    'affects_payable' => $this->isBillableActiveLine($line),
                ];
            });

        $discountLines = $invoice->discounts
            ->map(function ($discount): array {
                $status = $discount->status ?? 'active';

                return [
                    'id' => 'discount-'.$discount->id,
                    'item' => $discount->description,
                    'category' => 'Discount',
                    'type' => 'Discount',
                    'amount' => -1 * (float) $discount->amount,
                    'status' => $status,
                    'affects_payable' => $status === 'active',
                ];
            });

        return $chargeLines->concat($discountLines)->values();
    }

    private function mapInvoicePayments(StudentInvoice $invoice): Collection
    {
        $applications = $invoice->invoiceLines
            ->flatMap(fn (InvoiceLine $line) => $line->paymentApplications->map(fn (PaymentApplication $application) => [
                'payment_id' => $application->payment_id,
                'invoice_line_id' => $line->id,
                'paid_at' => $application->payment?->paid_at?->format('Y-m-d'),
                'method' => $application->payment?->method,
                'source' => $application->payment?->source,
                'payment_amount' => (float) ($application->payment?->amount ?? 0),
                'amount' => (float) $application->amount,
                'ref' => $application->payment?->external_ref,
                'charge_id' => $line->charge?->id,
                'charge_description' => $line->charge?->description ?? $line->description_snapshot,
            ]))
            ->filter(fn (array $row) => $row['payment_id'] !== null)
            ->groupBy('payment_id')
            ->map(function (Collection $paymentRows) {
                $firstRow = $paymentRows->first();
                $netAllocated = max(0, (float) $paymentRows->sum('amount'));

                if ($netAllocated <= 0) {
                    return null;
                }

                $charges = $paymentRows
                    ->groupBy('invoice_line_id')
                    ->map(function (Collection $chargeRows) {
                        $firstCharge = $chargeRows->first();
                        $netAllocated = max(0, (float) $chargeRows->sum('amount'));

                        if ($netAllocated <= 0) {
                            return null;
                        }

                        return [
                            'invoice_line_id' => $firstCharge['invoice_line_id'],
                            'charge_id' => $firstCharge['charge_id'],
                            'charge_description' => $firstCharge['charge_description'],
                            'allocated_amount' => $netAllocated,
                        ];
                    })
                    ->filter()
                    ->values();

                return [
                    'id' => $firstRow['payment_id'],
                    'paid_at' => $firstRow['paid_at'],
                    'method' => $firstRow['method'],
                    'source' => $firstRow['source'],
                    'amount' => $firstRow['payment_amount'],
                    'allocated_amount' => $netAllocated,
                    'ref' => $firstRow['ref'],
                    'charges' => $charges,
                ];
            })
            ->filter()
            ->values();

        return $applications;
    }

    private function buildTuitionPlanChecklist(Student $student, ?TuitionPlan $tuitionPlan, Collection $charges, Collection $invoices): ?array
    {
        if (! $tuitionPlan) {
            return null;
        }

        $intakeSemester = \App\Models\Semester::find($student->intake_semester_id);
        $semesterSequence = collect();

        if ($intakeSemester) {
            $semesterSequence = \App\Models\Semester::query()
                ->where('start_date', '>=', $intakeSemester->start_date)
                ->orderBy('start_date')
                ->limit(20)
                ->get();
        }

        $scholarshipAward = StudentScholarshipAward::query()
            ->where('student_id', $student->id)
            ->with('scholarshipDefinition')
            ->first();

        return [
            'plan_name' => $tuitionPlan->curriculumVersion->program->name.' ('.$tuitionPlan->curriculumVersion->version_code.')',
            'terms' => $tuitionPlan->terms->map(function (TuitionPlanTerm $term) use ($charges, $invoices, $semesterSequence, $scholarshipAward) {
                $inferredSemester = $semesterSequence->get($term->term_number - 1);

                $linkedCharge = $charges->first(function (FinanceCharge $charge) use ($term) {
                    return $charge->source_type === TuitionPlanTerm::class && (int) $charge->source_id === (int) $term->id;
                });

                if (! $linkedCharge && $inferredSemester) {
                    $linkedCharge = $charges->first(function (FinanceCharge $charge) use ($inferredSemester) {
                        return $charge->charge_type === FinanceCharge::TYPE_TUITION_TERM
                            && (int) $charge->semester_id === (int) $inferredSemester->id;
                    });
                }

                $generated = (bool) $linkedCharge;
                $linkedInvoiceDetails = [];
                $paidAmount = 0.0;

                if ($linkedCharge) {
                    $chargeLines = $linkedCharge->invoiceLines->filter(fn (InvoiceLine $line) => ($line->status ?? 'active') === 'active');
                    $paidAmount = (float) $chargeLines->sum(fn (InvoiceLine $line) => $this->sumNetPaymentApplications($line->paymentApplications));

                    foreach ($invoices as $invoice) {
                        if ($invoice->invoiceLines->contains('charge_id', $linkedCharge->id)) {
                            $linkedInvoiceDetails[] = [
                                'id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'status' => $invoice->status,
                                'due_date' => $invoice->due_date?->toDateString(),
                            ];
                        }
                    }
                }

                $discountAmount = 0.0;
                $isEstimatedDiscount = false;

                if ($linkedCharge) {
                    $linkedInvoiceIds = $linkedCharge->invoiceLines
                        ->pluck('invoice_id')
                        ->filter()
                        ->unique();

                    $discountAmount = (float) $invoices
                        ->whereIn('id', $linkedInvoiceIds)
                        ->sum(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['discount']);
                } elseif ($inferredSemester) {
                    $discountAmount = (float) $invoices
                        ->where('semester_id', $inferredSemester->id)
                        ->sum(fn (StudentInvoice $invoice) => $this->deriveInvoiceSnapshot($invoice)['discount']);
                }

                if ($discountAmount === 0.0 && $scholarshipAward?->scholarshipDefinition) {
                    $scholarship = $scholarshipAward->scholarshipDefinition;
                    $discountAmount = $scholarship->type === 'percentage'
                        ? ($term->amount * $scholarship->amount) / 100
                        : (float) $scholarship->amount;
                    $isEstimatedDiscount = $discountAmount > 0;
                }

                $amountDue = max(0, (float) $term->amount - $discountAmount);
                $paymentStatus = 'not_generated';

                if ($generated) {
                    if ($amountDue <= 0 || $paidAmount >= $amountDue) {
                        $paymentStatus = 'paid';
                    } elseif ($paidAmount > 0) {
                        $paymentStatus = 'partial';
                    } else {
                        $paymentStatus = 'unpaid';
                    }
                }

                return [
                    'term_number' => $term->term_number,
                    'semester_id' => $inferredSemester?->id,
                    'semester_name' => $inferredSemester?->name ?? 'Term '.$term->term_number,
                    'required_amount' => (float) $term->amount,
                    'discount_amount' => $discountAmount,
                    'is_estimated_discount' => $isEstimatedDiscount,
                    'amount_due' => $amountDue,
                    'paid_amount' => $paidAmount,
                    'generated' => $generated,
                    'charge_id' => $linkedCharge?->id,
                    'payment_status' => $paymentStatus,
                    'invoices' => $linkedInvoiceDetails,
                ];
            })->values(),
        ];
    }

    private function buildPaymentHistory(Collection $payments): Collection
    {
        return $payments->map(function (Payment $payment) {
            $netAllocated = $this->sumNetPaymentApplications($payment->applications);

            $allocations = $payment->applications
                ->groupBy('invoice_line_id')
                ->map(function (Collection $applications) {
                    /** @var PaymentApplication $firstApplication */
                    $firstApplication = $applications->first();
                    $line = $firstApplication->invoiceLine;
                    $invoice = $line?->invoice;
                    $netAllocated = max(0, (float) $applications->sum('amount'));

                    if (! $line || $netAllocated <= 0) {
                        return null;
                    }

                    return [
                        'allocation_id' => $line->id,
                        'allocated_amount' => $netAllocated,
                        'semester_name' => $line->charge?->semester?->name,
                        'invoice_id' => $invoice?->id,
                        'invoice_number' => $invoice?->invoice_number,
                        'charge_id' => $line->charge?->id,
                        'charge_description' => $line->charge?->description ?? $line->description_snapshot,
                    ];
                })
                ->filter()
                ->values();

            return [
                'id' => $payment->id,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'method' => $payment->method,
                'source' => $payment->source,
                'amount' => (float) $payment->amount,
                'allocated_amount' => $netAllocated,
                'unapplied_amount' => max(0, (float) $payment->amount - $netAllocated),
                'ref' => $payment->external_ref,
                'allocations' => $allocations,
            ];
        })->values();
    }

    private function buildStatementEvents(Collection $payments): Collection
    {
        $events = $payments
            ->flatMap(function (Payment $payment) {
                $paymentEvent = collect([[
                    'event_key' => 'payment-'.$payment->id,
                    'event_at' => $payment->paid_at,
                    'sort_order' => 0,
                    'kind' => 'payment',
                    'label' => 'Payment received',
                    'reference' => 'Payment #'.$payment->id,
                    'details' => collect([
                        ucfirst(str_replace('_', ' ', $payment->method)),
                        $payment->source ? 'Source: '.$payment->source : null,
                        $payment->external_ref ? 'Ref: '.$payment->external_ref : null,
                    ])->filter()->implode(' • '),
                    'money_in' => (float) $payment->amount,
                    'money_out' => 0.0,
                ]]);

                $applicationEvents = $payment->applications->map(function (PaymentApplication $application) {
                    $line = $application->invoiceLine;
                    $invoice = $line?->invoice;
                    $details = collect([
                        $line?->charge?->semester?->name,
                        $line?->charge?->description ?? $line?->description_snapshot,
                    ])->filter()->implode(' • ');

                    $isRelease = (float) $application->amount < 0;

                    return [
                        'event_key' => ($isRelease ? 'release-' : 'application-').$application->id,
                        'event_at' => $application->applied_at ?? $application->created_at,
                        'sort_order' => 1,
                        'kind' => $isRelease ? 'release' : 'application',
                        'label' => $isRelease ? 'Released from invoice' : 'Applied to invoice',
                        'reference' => $invoice?->invoice_number ?? 'Unlinked invoice line',
                        'details' => $details,
                        'money_in' => $isRelease ? abs((float) $application->amount) : 0.0,
                        'money_out' => $isRelease ? 0.0 : (float) $application->amount,
                    ];
                });

                return $paymentEvent->concat($applicationEvents);
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

        $runningBalance = 0.0;

        return $events->map(function (array $event) use (&$runningBalance) {
            $runningBalance += $event['money_in'];
            $runningBalance -= $event['money_out'];

            return [
                'event_key' => $event['event_key'],
                'event_at' => $event['event_at']?->format('Y-m-d H:i'),
                'kind' => $event['kind'],
                'label' => $event['label'],
                'reference' => $event['reference'],
                'details' => $event['details'],
                'money_in' => $event['money_in'],
                'money_out' => $event['money_out'],
                'unapplied_balance' => $runningBalance,
            ];
        })->values();
    }

    private function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    {
        $subtotal = max(
            (float) $invoice->subtotal,
            (float) $invoice->invoiceLines
                ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line) && (float) $line->amount_snapshot > 0)
                ->sum('amount_snapshot')
        );

        $discount = max(
            (float) $invoice->discount_total,
            (float) $invoice->discounts
                ->filter(fn ($discount) => ($discount->status ?? 'active') === 'active')
                ->sum('amount')
        );

        $paid = (float) $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
            ->sum(fn (InvoiceLine $line) => $this->sumNetPaymentApplications($line->paymentApplications));

        $total = max(0, $subtotal - $discount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'paid' => min($paid, $total),
            'remaining' => max(0, $total - $paid),
        ];
    }

    private function sumNetPaymentApplications(Collection $applications): float
    {
        return max(0, (float) $applications->sum('amount'));
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE;
    }

    private function getChargeCategory(string $type): string
    {
        return match ($type) {
            FinanceCharge::TYPE_TUITION_TERM => 'Tuition',
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'EGC',
            FinanceCharge::TYPE_RETAKE_FEE => 'Retake',
            default => 'Other',
        };
    }
}
