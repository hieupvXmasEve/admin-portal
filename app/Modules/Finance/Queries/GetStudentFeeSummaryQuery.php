<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\Finance\StudentFeeSummaryReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class GetStudentFeeSummaryQuery implements StudentFeeSummaryReader
{
    /** @var Collection<int, array<string, float|bool|array<int, string>>> */
    private Collection $invoiceSnapshots;

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly StudentFinanceSettlementPositionReader $studentSettlementPositionReader,
        private readonly StudentReferenceReader $students,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly GetActiveScholarshipAdjustmentQuery $scholarshipAdjustments,
    ) {}

    public function execute(int $studentId): array
    {
        $student = $this->students->find($studentId);
        if ($student === null) {
            throw (new ModelNotFoundException)->setModel('Student', [$studentId]);
        }

        return $this->build($student, $this->programEnrollments->forStudentId($studentId));
    }

    private function build(StudentReference $student, ProgramEnrollmentSummary $enrollment): array
    {
        $tuitionPlan = TuitionPlan::query()
            ->where('curriculum_version_id', $enrollment->curriculumVersionId)
            ->where('intake_semester_id', $enrollment->intakeSemesterId)
            ->where('is_active', true)
            ->with('terms')
            ->first();

        $invoices = StudentInvoice::query()
            ->where('student_id', $student->id)
            ->with([
                'invoiceLines.charge.financeObligation',
                'invoiceLines.paymentApplications.payment',
                'discounts.allocations.invoiceLine.charge',
            ])
            ->orderBy('due_date')
            ->get();

        $this->invoiceSnapshots = $this->invoiceSnapshots($invoices);

        $charges = FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->with(['financeObligation', 'invoiceLines.paymentApplications', 'invoiceLines.invoice'])
            ->get();

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->with([
                'applications.invoiceLine.charge',
                'applications.invoiceLine.invoice',
            ])
            ->orderByDesc('paid_at')
            ->get();

        $academicPeriodIds = $invoices->pluck('semester_id')
            ->concat($charges->pluck('semester_id'))
            ->when(
                $enrollment->intakeMajorSemesterId !== null,
                fn (Collection $ids): Collection => $ids->push($enrollment->intakeMajorSemesterId),
            )
            ->filter()
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $periods = collect($this->academicPeriods->findMany($academicPeriodIds));
        $studentPosition = $this->studentSettlementPositionReader->current((int) $student->id);

        $billingBySemester = $this->buildBillingBySemester($invoices, $periods);
        $checklist = $this->buildTuitionPlanChecklist(
            $student,
            $enrollment,
            $tuitionPlan,
            $charges,
            $invoices,
            $this->chargeSnapshots($charges),
            $periods,
        );

        $valid = (bool) $studentPosition['valid'];
        $totalCharged = $valid ? (float) $studentPosition['gross'] : null;
        $totalDiscount = $valid ? (float) $studentPosition['discount'] : null;
        $totalAllocated = $valid ? (float) $studentPosition['cash_applied'] : null;
        $totalCreditApplied = $valid ? (float) $studentPosition['credit_applied'] : null;
        $netDue = $valid ? (float) $studentPosition['net_due'] : null;
        $outstanding = $valid ? (float) $studentPosition['remaining_collectible'] : null;
        $totalPayments = $valid ? (float) $studentPosition['total_cash_received'] : null;
        $totalUnapplied = $valid ? (float) $studentPosition['unapplied_cash'] : null;

        return [
            'student_info' => [
                'full_name' => $student->fullName,
                'student_id' => $student->studentCode,
                'program' => $enrollment->programName,
                'intake' => $enrollment->intakeSemesterName,
            ],
            'summary' => [
                'total_charged' => $totalCharged,
                'total_discount' => $totalDiscount,
                'active_due' => $netDue,
                'total_paid' => $totalPayments,
                'total_allocated' => $totalAllocated,
                'total_credit_applied' => $totalCreditApplied,
                'outstanding' => $outstanding,
                'unapplied_balance' => $totalUnapplied,
                'remaining' => $outstanding,
                'net_amount_to_collect' => $outstanding,
                'payment_count' => $payments->count(),
                'progress' => $valid && $netDue > 0 ? min(100, max(0, ($totalAllocated / $netDue) * 100)) : null,
                'settlement_position' => [
                    'valid' => $valid,
                    'issues' => $valid ? [] : collect($studentPosition['issues'])
                        ->pluck('code')
                        ->unique()
                        ->values()
                        ->all(),
                ],
            ],
            'payments' => $this->buildPaymentHistory($payments, $periods),
            'statement_events' => $this->buildStatementEvents($payments, $invoices, $periods),
            'billing_by_semester' => $billingBySemester,
            'tuition_plan_checklist' => $checklist,
        ];
    }

    private function buildBillingBySemester(Collection $invoices, Collection $periods): Collection
    {
        // Exclude cancelled invoices from billing display — they carry no financial obligation
        $activeInvoices = $invoices->reject(fn (StudentInvoice $invoice) => $invoice->status === 'cancelled');

        $groupedInvoices = $activeInvoices->groupBy('semester_id');
        $semesters = $activeInvoices->pluck('semester_id')
            ->filter()
            ->unique()
            ->map(fn (int|string $semesterId) => $periods->get((int) $semesterId))
            ->filter()
            ->sortBy('start_date');

        return $semesters->map(function ($semester) use ($groupedInvoices) {
            $semesterInvoices = $groupedInvoices->get($semester->id, collect());
            $lineIds = $semesterInvoices
                ->flatMap(static fn (StudentInvoice $invoice) => $invoice->invoiceLines->pluck('id'))
                ->map(static fn (int|string $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $position = $this->settlementPositionReader->forPayableLines($lineIds);
            $valid = $position->isValid() && $position->amounts !== null;
            $amounts = $position->amounts;
            $totals = [
                'valid' => $valid,
                'total' => $valid ? (float) $amounts->netDue()->amount : null,
                'paid' => $valid ? (float) $amounts->cash->amount : null,
                'credit' => $valid ? (float) $amounts->credit->amount : null,
                'remaining' => $valid ? (float) $amounts->remaining->amount : null,
            ];

            $remaining = $totals['valid'] ? $totals['remaining'] : null;

            $status = $totals['valid'] ? 'paid' : 'review_required';
            if ($semesterInvoices->isEmpty()) {
                $status = 'no_invoices';
            } elseif ($remaining !== null && $remaining > 0) {
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
                    'credit' => $totals['credit'],
                    'remaining' => $remaining,
                ],
                // Presentational only — NEVER injected into the invoice line
                // collection (FeeTab sums affects_payable lines and would
                // double-count a synthetic breakdown row).
                'scholarship_breakdown' => $this->buildScholarshipBreakdown($semesterInvoices, (int) $semester->id),
                'invoices' => $semesterInvoices->map(fn (StudentInvoice $invoice) => $this->mapInvoiceSummary($invoice))->values(),
            ];
        })->values();
    }

    /**
     * "GIẢM TRỪ HỌC BỔNG" breakdown for a semester carrying an active
     * scholarship adjustment. Original terms come from the adjustment's
     * SNAPSHOT columns (the award may have mutated since approval); the
     * effective amount is ledger truth (active scholarship discount rows).
     *
     * @return array{original_amount: float, deduction_amount: float, effective_amount: float, adjustment_status: string, label: string}|null
     */
    private function buildScholarshipBreakdown(Collection $semesterInvoices, int $semesterId): ?array
    {
        $studentId = (int) ($semesterInvoices->first()?->student_id ?? 0);

        if ($studentId === 0) {
            return null;
        }

        $adjustment = $this->scholarshipAdjustments->handle($studentId, $semesterId);

        if ($adjustment === null) {
            return null;
        }

        $tuitionBase = (float) $semesterInvoices
            ->flatMap(static fn (StudentInvoice $invoice) => $invoice->invoiceLines)
            ->filter(fn (InvoiceLine $line): bool => $line->status === 'active'
                && $line->charge?->charge_type === FinanceCharge::TYPE_TUITION_TERM)
            ->sum('amount_snapshot');

        $originalValue = (float) $adjustment->original_amount;
        $originalAmount = $adjustment->original_type === 'percentage'
            ? ($tuitionBase * $originalValue) / 100
            : $originalValue;
        $originalAmount = max(0.0, min($tuitionBase, $originalAmount));

        $effectiveAmount = (float) $semesterInvoices
            ->flatMap(static fn (StudentInvoice $invoice) => $invoice->discounts)
            ->filter(static fn (InvoiceDiscount $discount): bool => $discount->discount_type === 'scholarship'
                && ($discount->status ?? 'active') === 'active')
            ->sum('amount');

        return [
            'original_amount' => round($originalAmount, 2),
            'deduction_amount' => round(max(0.0, $originalAmount - $effectiveAmount), 2),
            'effective_amount' => round($effectiveAmount, 2),
            'adjustment_status' => (string) $adjustment->status,
            'label' => 'GIẢM TRỪ HỌC BỔNG',
        ];
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
            'credit' => $snapshot['credit'],
            'remaining' => $snapshot['remaining'],
            'final_payable' => $snapshot['total'],
            'cash_applied' => $snapshot['paid'],
            'settled_amount' => $snapshot['paid'],
            'snapshot_total' => $snapshot['subtotal'],
            'discount_total' => $snapshot['discount'],
            'lines' => $this->mapInvoiceLines($invoice),
            'payments' => $this->mapInvoicePayments($invoice),
            'discounts' => $this->mapInvoiceDiscounts($invoice),
            'discount_allocated' => (float) $invoice->discounts
                ->flatMap(fn (InvoiceDiscount $discount) => $discount->allocations)
                ->where('entry_type', 'allocation')
                ->sum('amount'),
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

    /** @param Collection<int, array<string, float|bool|array<int, string>|null>> $chargeSnapshots */
    private function buildTuitionPlanChecklist(
        StudentReference $student,
        ProgramEnrollmentSummary $enrollment,
        ?TuitionPlan $tuitionPlan,
        Collection $charges,
        Collection $invoices,
        Collection $chargeSnapshots,
        Collection $periods,
    ): ?array {
        if (! $tuitionPlan) {
            return null;
        }

        $scholarshipAward = StudentScholarshipAward::query()
            ->where('student_id', $student->id)
            ->with('scholarshipDefinition')
            ->first();

        $intakeMajorSemester = $enrollment->intakeMajorSemesterId === null
            ? null
            : $periods->get($enrollment->intakeMajorSemesterId);
        $planTermsById = $tuitionPlan->terms->keyBy('id');
        $planTermsByNumber = $tuitionPlan->terms->keyBy('term_number');

        // Source of truth: actual tuition charges, sorted by semester date
        $tuitionCharges = $charges
            ->filter(fn (FinanceCharge $c) => $c->charge_type === FinanceCharge::TYPE_TUITION_TERM)
            ->sortBy(fn (FinanceCharge $c) => $c->semester_id === null
                ? 0
                : ($periods->get((int) $c->semester_id)?->start_date?->timestamp ?? 0));

        $usedPlanTermIds = [];

        // Build items from actual generated charges
        $generatedItems = $tuitionCharges->map(function (FinanceCharge $charge) use (
            $planTermsById,
            $planTermsByNumber,
            $intakeMajorSemester,
            $invoices,
            $chargeSnapshots,
            $periods,
            &$usedPlanTermIds,
        ) {
            $pricingSnapshot = $charge->financeObligation?->pricing_snapshot ?? [];
            $linkedTerm = isset($pricingSnapshot['tuition_plan_term_id'])
                ? $planTermsById->get((int) $pricingSnapshot['tuition_plan_term_id'])
                : null;
            if ($linkedTerm === null && isset($pricingSnapshot['term_number'])) {
                $linkedTerm = $planTermsByNumber->get((int) $pricingSnapshot['term_number']);
            }

            $chargeSemester = $charge->semester_id === null
                ? null
                : $periods->get((int) $charge->semester_id);
            if ($linkedTerm === null
                && $intakeMajorSemester?->start_date !== null
                && $chargeSemester?->start_date !== null) {
                $termNumber = $this->academicPeriods->countStartingBetween(
                    $intakeMajorSemester->start_date,
                    $chargeSemester->start_date,
                );
                $linkedTerm = $planTermsByNumber->get($termNumber);
            }
            if ($linkedTerm) {
                $usedPlanTermIds[] = $linkedTerm->id;
            }

            $snapshot = $chargeSnapshots->get((int) $charge->id);
            $valid = $snapshot['valid'] ?? false;
            $gross = $valid ? $snapshot['gross'] : null;
            $discountAmount = $valid ? $snapshot['discount'] : null;
            $amountDue = $valid ? $snapshot['net_due'] : null;
            $paidAmount = $valid ? $snapshot['cash'] : null;
            $creditAmount = $valid ? $snapshot['credit'] : null;
            $remaining = $valid ? $snapshot['remaining'] : null;

            $paymentStatus = match (true) {
                ! $valid => 'review_required',
                $remaining <= 0 => 'paid',
                $paidAmount > 0 || $creditAmount > 0 => 'partial',
                default => 'unpaid',
            };

            $linkedInvoiceDetails = [];
            foreach ($invoices as $invoice) {
                if ($invoice->invoiceLines->contains('charge_id', $charge->id)) {
                    $linkedInvoiceDetails[] = [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'due_date' => $invoice->due_date?->toDateString(),
                    ];
                }
            }

            return [
                'term_number' => $linkedTerm?->term_number,
                'semester_id' => $charge->semester_id,
                'semester_name' => $chargeSemester?->name,
                'required_amount' => $gross,
                'discount_amount' => $discountAmount,
                'credit_amount' => $creditAmount,
                'remaining_amount' => $remaining,
                'is_estimated_discount' => false,
                'amount_due' => $amountDue,
                'paid_amount' => $paidAmount,
                'generated' => true,
                'charge_id' => $charge->id,
                'payment_status' => $paymentStatus,
                'settlement_position' => [
                    'valid' => $valid,
                    'issues' => $snapshot['issues'] ?? ['missing_charge_position'],
                ],
                'invoices' => $linkedInvoiceDetails,
            ];
        })->values();

        // Append unmatched plan terms as projected — no semester assigned to avoid false inference
        $projectedItems = $tuitionPlan->terms
            ->filter(fn (TuitionPlanTerm $term) => ! in_array($term->id, $usedPlanTermIds))
            ->map(function (TuitionPlanTerm $term) use ($scholarshipAward) {
                $isWaived = (float) $term->amount === 0.0;
                $discountAmount = 0.0;
                $isEstimatedDiscount = false;

                if (! $isWaived && $scholarshipAward?->scholarshipDefinition) {
                    $scholarship = $scholarshipAward->scholarshipDefinition;
                    $discountAmount = $scholarship->type === 'percentage'
                        ? ((float) $term->amount * $scholarship->amount) / 100
                        : (float) $scholarship->amount;
                    $isEstimatedDiscount = $discountAmount > 0;
                }

                return [
                    'term_number' => $term->term_number,
                    'semester_id' => null,
                    'semester_name' => null, // Intentionally null: no semester inferred for ungenerated terms
                    'required_amount' => (float) $term->amount,
                    'discount_amount' => $discountAmount,
                    'is_estimated_discount' => $isEstimatedDiscount,
                    'amount_due' => max(0, (float) $term->amount - $discountAmount),
                    'paid_amount' => 0.0,
                    'generated' => false,
                    'charge_id' => null,
                    'payment_status' => $isWaived ? 'waived' : 'not_generated',
                    'invoices' => [],
                ];
            })->values();

        return [
            'plan_id' => $tuitionPlan->id,
            'plan_name' => ($enrollment->programName ?? $student->programName ?? 'N/A')
                .' ('.($enrollment->curriculumVersionCode ?? 'N/A').')',
            'terms' => $generatedItems->concat($projectedItems),
        ];
    }

    private function buildPaymentHistory(Collection $payments, Collection $periods): Collection
    {
        return $payments->map(function (Payment $payment) use ($periods) {
            $netAllocated = $this->sumNetPaymentApplications($payment->applications);

            $allocations = $payment->applications
                ->groupBy('invoice_line_id')
                ->map(function (Collection $applications) use ($periods) {
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
                        'semester_name' => $line->charge?->semester_id === null
                            ? null
                            : $periods->get((int) $line->charge->semester_id)?->name,
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

    private function buildStatementEvents(Collection $payments, Collection $invoices, Collection $periods): Collection
    {
        $paymentEvents = $payments
            ->flatMap(function (Payment $payment) use ($periods) {
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

                $applicationEvents = $payment->applications->map(function (PaymentApplication $application) use ($periods) {
                    $line = $application->invoiceLine;
                    $invoice = $line?->invoice;
                    $details = collect([
                        $line?->charge?->semester_id === null
                            ? null
                            : $periods->get((int) $line->charge->semester_id)?->name,
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
            });

        $discountEvents = $invoices
            ->flatMap(function (StudentInvoice $invoice) use ($periods) {
                return $invoice->discounts->flatMap(function (InvoiceDiscount $discount) use ($invoice, $periods) {
                    $allocatedAmount = (float) $discount->allocations
                        ->where('entry_type', 'allocation')
                        ->sum('amount');

                    if ($allocatedAmount <= 0) {
                        return [];
                    }

                    $details = $discount->allocations
                        ->where('entry_type', 'allocation')
                        ->map(function ($allocation) use ($periods) {
                            $line = $allocation->invoiceLine;

                            return collect([
                                $line?->charge?->semester_id === null
                                    ? null
                                    : $periods->get((int) $line->charge->semester_id)?->name,
                                $line?->charge?->description ?? $line?->description_snapshot,
                            ])->filter()->implode(' • ');
                        })
                        ->filter()
                        ->unique()
                        ->implode(' | ');

                    return [[
                        'event_key' => 'discount-'.$discount->id,
                        'event_at' => $discount->created_at,
                        'sort_order' => 1,
                        'kind' => 'discount',
                        'label' => 'Discount applied',
                        'reference' => $invoice->invoice_number,
                        'details' => collect([
                            $discount->description,
                            $details ?: null,
                        ])->filter()->implode(' • '),
                        'money_in' => 0.0,
                        'money_out' => $allocatedAmount,
                    ]];
                });
            });

        $events = $paymentEvents
            ->concat($discountEvents)
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
            if ($event['kind'] !== 'discount') {
                $runningBalance += $event['money_in'];
                $runningBalance -= $event['money_out'];
            }

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

    private function mapInvoiceDiscounts(StudentInvoice $invoice): Collection
    {
        return $invoice->discounts
            ->filter(fn (InvoiceDiscount $discount) => ($discount->status ?? 'active') === 'active')
            ->map(function (InvoiceDiscount $discount) {
                $allocatedAmount = (float) $discount->allocations
                    ->where('entry_type', 'allocation')
                    ->sum('amount');

                $targets = $discount->allocations
                    ->where('entry_type', 'allocation')
                    ->map(function ($allocation) {
                        $line = $allocation->invoiceLine;

                        return [
                            'invoice_line_id' => $line?->id,
                            'charge_id' => $line?->charge?->id,
                            'charge_description' => $line?->charge?->description ?? $line?->description_snapshot,
                            'allocated_amount' => (float) $allocation->amount,
                        ];
                    })
                    ->filter(fn (array $target) => $target['invoice_line_id'] !== null)
                    ->values();

                return [
                    'id' => $discount->id,
                    'created_at' => $discount->created_at?->format('Y-m-d'),
                    'description' => $discount->description,
                    'discount_type' => $discount->discount_type,
                    'amount' => (float) $discount->amount,
                    'allocated_amount' => $allocatedAmount,
                    'targets' => $targets,
                ];
            })
            ->values();
    }

    private function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    {
        // Cancelled invoices contribute nothing to the fee summary
        if ($invoice->status === 'cancelled') {
            return ['valid' => true, 'issues' => [], 'subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'paid' => 0.0, 'credit' => 0.0, 'remaining' => 0.0];
        }

        return $this->invoiceSnapshots->get((int) $invoice->id, [
            'valid' => false,
            'issues' => ['missing_invoice_position'],
            'subtotal' => null,
            'discount' => null,
            'total' => null,
            'paid' => null,
            'credit' => null,
            'remaining' => null,
        ]);
    }

    /** @return Collection<int, array<string, float|bool|array<int, string>>> */
    private function invoiceSnapshots(Collection $invoices): Collection
    {
        $positions = $this->settlementPositionReader->batch(
            $invoices->map(static fn (StudentInvoice $invoice) => SettlementPositionScope::invoice((int) $invoice->id))->all(),
        );

        return $invoices->values()->mapWithKeys(function (StudentInvoice $invoice, int $index) use ($positions): array {
            $position = $positions[$index] ?? null;
            $valid = $position?->isValid() && $position->amounts !== null;
            $amounts = $position?->amounts;

            return [(int) $invoice->id => [
                'valid' => $valid,
                'issues' => $valid ? [] : array_map(static fn ($issue): string => $issue->code, $position?->issues ?? []),
                'subtotal' => $valid ? (float) $amounts->gross->amount : null,
                'discount' => $valid ? (float) $amounts->discount->amount : null,
                'total' => $valid ? (float) $amounts->netDue()->amount : null,
                'paid' => $valid ? (float) $amounts->cash->amount : null,
                'credit' => $valid ? (float) $amounts->credit->amount : null,
                'remaining' => $valid ? (float) $amounts->remaining->amount : null,
            ]];
        });
    }

    /** @return Collection<int, array<string, float|bool|array<int, string>|null>> */
    private function chargeSnapshots(Collection $charges): Collection
    {
        $positions = $charges->isEmpty()
            ? []
            : $this->settlementPositionReader->batch(
                $charges->map(static fn (FinanceCharge $charge): SettlementPositionScope => SettlementPositionScope::payableLines(
                    $charge->invoiceLines
                        ->pluck('id')
                        ->map(static fn (int|string $id): int => (int) $id)
                        ->all(),
                ))->all(),
            );

        return $charges->values()->mapWithKeys(function (FinanceCharge $charge, int $index) use ($positions): array {
            $position = $positions[$index] ?? null;
            $valid = $position?->isValid() && $position->amounts !== null;
            $amounts = $position?->amounts;

            return [(int) $charge->id => [
                'valid' => $valid,
                'issues' => $valid
                    ? []
                    : array_map(static fn ($issue): string => $issue->code, $position?->issues ?? []),
                'gross' => $valid ? (float) $amounts->gross->amount : null,
                'discount' => $valid ? (float) $amounts->discount->amount : null,
                'net_due' => $valid ? (float) $amounts->netDue()->amount : null,
                'cash' => $valid ? (float) $amounts->cash->amount : null,
                'credit' => $valid ? (float) $amounts->credit->amount : null,
                'remaining' => $valid ? (float) $amounts->remaining->amount : null,
            ]];
        });
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
