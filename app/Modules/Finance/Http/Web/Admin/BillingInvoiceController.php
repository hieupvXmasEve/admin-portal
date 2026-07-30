<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Http\Requests\Lookup\FilterStudentInvoicesRequest;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Lookup\ListStudentInvoicesQuery;
use App\Modules\Finance\Queries\Student360\GetStudentFinancePaymentHistoryQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionAmounts;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * @phpstan-type MoneyPayload array{amount:string,currency:string,scale:int,minor_amount:int}
 * @phpstan-type AmountsPayload array{gross:MoneyPayload,discount:MoneyPayload,cash:MoneyPayload,credit:MoneyPayload,remaining:MoneyPayload}
 * @phpstan-type IssuePayload array{code:string,severity:string,blocking:bool,evidence:array<string,int|string>,finance_invariant_code:?string}
 * @phpstan-type RawEvidencePayload array{gross:MoneyPayload,discount:MoneyPayload,cash:MoneyPayload,credit:MoneyPayload,remaining:MoneyPayload,reversed_discount_residue:MoneyPayload,inactive_credit_residue:MoneyPayload}
 * @phpstan-type PositionPayload array{scope_type:string,scope_id:int,payable_line_id:?int,finance_obligation_id:?int,invoice_id:?int,billing_account_id:?int,fee_type:?string,position_mode:string,captured_at:string,snapshot_version:string,settlement_state:string,valid:bool,amounts:?AmountsPayload,raw_evidence:RawEvidencePayload,issues:list<IssuePayload>,payable_line_breakdown:list<PositionPayload>,breakdown_reconciliation:array{status:string,rounding_remainder:MoneyPayload}}
 */
class BillingInvoiceController extends Controller
{
    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly GetStudentFinancePaymentHistoryQuery $paymentHistoryQuery,
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
    ) {}

    public function index(
        FilterStudentInvoicesRequest $request,
        ListStudentInvoicesQuery $query,
    ) {
        return Inertia::render('Finance/Invoices/Index', [
            'invoices' => $query->handle($request)['items'],
            'filters' => array_merge(
                $request->only(['search', 'status', 'sort', 'direction', 'per_page']),
                ['semester_id' => FinanceSemesterContextResolver::selectedId()],
            ),
        ]);
    }

    public function show(StudentInvoice $invoice)
    {
        $student = $this->studentReferences->find((int) $invoice->student_id);
        abort_if($student === null, 404);

        // Ensure campus check
        if ($campusId = app('campus')?->id) {
            if ($student->campusId !== (int) $campusId) {
                abort(403, 'This invoice does not belong to your campus.');
            }
        }

        $enrollment = $this->programEnrollments->forStudentId($student->id);

        $invoice->load([
            'semester',
            'invoiceLines.charge',
            'invoiceLines.paymentApplications.payment',
            'invoiceLines.discountAllocations.invoiceDiscount',
            'billingCycle',
        ]);

        $lines = $invoice->invoiceLines->values();
        $settlementPosition = $this->settlementPositionReader->forInvoice((int) $invoice->id);
        $paymentHistory = collect($this->paymentHistoryQuery->handle((int) $invoice->student_id))->keyBy('id');

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student' => [
                    'id' => $student->id,
                    'full_name' => $student->fullName,
                    'student_id' => $student->studentCode,
                    'email' => $student->email,
                    'program' => $enrollment->programName !== null ? [
                        'name' => $enrollment->programName,
                    ] : null,
                ],
                'semester' => [
                    'id' => $invoice->semester->id,
                    'name' => $invoice->semester->name,
                ],
                'billing_cycle' => $invoice->billingCycle ? [
                    'name' => $invoice->billingCycle->name,
                ] : null,
                'due_date' => $invoice->due_date?->toIso8601String(),
                'created_at' => $invoice->created_at?->toIso8601String(),
                'status' => $invoice->status,
                'settlement_position' => $this->mapPosition($settlementPosition),
                'charges' => $this->mapCharges($lines, $settlementPosition),
                'settlement_entries' => $this->mapSettlementEntries($lines, $paymentHistory),
            ],
        ]);
    }

    private function mapCharges(Collection $lines, SettlementPosition $invoicePosition): array
    {
        $linePositions = collect($invoicePosition->payable_line_breakdown)
            ->keyBy(fn (SettlementPosition $position): int => (int) $position->payable_line_id);

        return $lines
            ->map(function (InvoiceLine $line) use ($linePositions): array {
                $position = $linePositions->get((int) $line->id);

                return [
                    'id' => $line->charge?->id,
                    'invoice_line_id' => $line->id,
                    'charge_type' => $line->charge?->charge_type,
                    'description' => $line->description_snapshot ?: $line->charge?->description,
                    'amounts' => $position?->amounts ? $this->mapAmounts($position->amounts) : null,
                    'valid' => $position?->isValid() ?? false,
                    'settlement_state' => $position?->settlement_state,
                    'issues' => $position === null ? [] : $this->mapIssues($position->issues),
                    'effective_at' => $line->charge?->effective_at?->toIso8601String(),
                    'source_type' => $line->charge?->charge_type === FinanceCharge::TYPE_MANUAL_FEE
                        ? 'Manual'
                        : null,
                    'status' => $line->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{id:int, surplus_amount:float, status:string}>  $paymentHistory
     */
    private function mapSettlementEntries(Collection $lines, Collection $paymentHistory): array
    {
        $paymentEntries = $lines->flatMap(function (InvoiceLine $line) use ($paymentHistory): Collection {
            return $line->paymentApplications->map(function (PaymentApplication $application) use ($line, $paymentHistory): array {
                $paymentSummary = $paymentHistory->get($application->payment?->id);

                return [
                    'id' => "payment-{$application->id}",
                    'entry_group' => 'payment',
                    'entry_type' => $application->entry_type,
                    'applied_at' => $application->applied_at?->toIso8601String(),
                    'amount' => (string) $application->amount,
                    'charge' => [
                        'id' => $line->charge?->id,
                        'description' => $line->description_snapshot,
                        'charge_type' => $line->charge?->charge_type,
                    ],
                    'payment' => $application->payment ? [
                        'id' => $application->payment->id,
                        'amount' => (string) $application->payment->amount,
                        'paid_at' => $application->payment->paid_at?->toIso8601String(),
                        'method' => $application->payment->method,
                        'status' => $application->payment->status,
                        'external_ref' => $application->payment->external_ref,
                        'surplus_amount' => $paymentSummary['surplus_amount'] ?? 0,
                        'is_fully_allocated' => ($paymentSummary['status'] ?? null) !== 'surplus',
                    ] : null,
                    'discount' => null,
                ];
            });
        });

        $discountEntries = $lines->flatMap(function (InvoiceLine $line): Collection {
            return $line->discountAllocations->map(function (DiscountAllocation $allocation) use ($line): array {
                return [
                    'id' => "discount-{$allocation->id}",
                    'entry_group' => 'discount',
                    'entry_type' => $allocation->entry_type,
                    'applied_at' => $allocation->created_at?->toIso8601String(),
                    'amount' => (string) $allocation->amount,
                    'charge' => [
                        'id' => $line->charge?->id,
                        'description' => $line->description_snapshot,
                        'charge_type' => $line->charge?->charge_type,
                    ],
                    'payment' => null,
                    'discount' => $allocation->invoiceDiscount ? [
                        'id' => $allocation->invoiceDiscount->id,
                        'description' => $allocation->invoiceDiscount->description,
                        'discount_type' => $allocation->invoiceDiscount->discount_type,
                        'discount_source' => $allocation->invoiceDiscount->discount_source,
                    ] : null,
                ];
            });
        });

        return $paymentEntries
            ->concat($discountEntries)
            ->sortByDesc(fn (array $entry) => $entry['applied_at'] ?? '')
            ->values()
            ->all();
    }

    /** @return PositionPayload */
    private function mapPosition(SettlementPosition $position): array
    {
        return [
            'scope_type' => $position->scope_type,
            'scope_id' => $position->scope_id,
            'payable_line_id' => $position->payable_line_id,
            'finance_obligation_id' => $position->finance_obligation_id,
            'invoice_id' => $position->invoice_id,
            'billing_account_id' => $position->billing_account_id,
            'fee_type' => $position->fee_type,
            'position_mode' => $position->position_mode,
            'captured_at' => $position->captured_at->toIso8601String(),
            'snapshot_version' => $position->snapshot_version,
            'settlement_state' => $position->settlement_state,
            'valid' => $position->isValid(),
            'amounts' => $position->amounts ? $this->mapAmounts($position->amounts) : null,
            'raw_evidence' => $this->mapRawEvidence($position),
            'issues' => $this->mapIssues($position->issues),
            'breakdown_reconciliation' => [
                'status' => $position->isValid() ? 'exact' : 'invalid',
                'rounding_remainder' => $this->mapMoney(Money::zero()),
            ],
            'payable_line_breakdown' => array_map(
                fn (SettlementPosition $linePosition): array => $this->mapPosition($linePosition),
                $position->payable_line_breakdown,
            ),
        ];
    }

    /** @return AmountsPayload */
    private function mapAmounts(SettlementPositionAmounts $amounts): array
    {
        return [
            'gross' => $this->mapMoney($amounts->gross),
            'discount' => $this->mapMoney($amounts->discount),
            'cash' => $this->mapMoney($amounts->cash),
            'credit' => $this->mapMoney($amounts->credit),
            'remaining' => $this->mapMoney($amounts->remaining),
        ];
    }

    /** @return RawEvidencePayload */
    private function mapRawEvidence(SettlementPosition $position): array
    {
        return [
            'gross' => $this->mapMoney($position->raw_evidence->gross),
            'discount' => $this->mapMoney($position->raw_evidence->discount),
            'cash' => $this->mapMoney($position->raw_evidence->cash),
            'credit' => $this->mapMoney($position->raw_evidence->credit),
            'remaining' => $this->mapMoney($position->raw_evidence->remaining),
            'reversed_discount_residue' => $this->mapMoney($position->raw_evidence->reversed_discount_residue),
            'inactive_credit_residue' => $this->mapMoney($position->raw_evidence->inactive_credit_residue),
        ];
    }

    /**
     * @return array{amount:string,currency:string,scale:int,minor_amount:int}
     */
    private function mapMoney(Money $money): array
    {
        return [
            'amount' => $money->amount,
            'currency' => $money->currency,
            'scale' => $money->scale,
            'minor_amount' => $money->minor_amount,
        ];
    }

    /**
     * @param  list<SettlementPositionIssue>  $issues
     * @return list<IssuePayload>
     */
    private function mapIssues(array $issues): array
    {
        return array_map(static fn (SettlementPositionIssue $issue): array => [
            'code' => $issue->code,
            'severity' => $issue->severity,
            'blocking' => $issue->blocking,
            'evidence' => $issue->evidence,
            'finance_invariant_code' => $issue->finance_invariant_code,
        ], $issues);
    }

    public function voidLine(Request $request, StudentInvoice $invoice, InvoiceLine $line, VoidFinanceChargeAction $action)
    {
        if ((int) $line->invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        if (! $line->charge_id) {
            return back()->withErrors(['error' => 'Invoice line is not linked to a finance charge.']);
        }

        $action->handle((int) $line->charge_id, $validated['void_reason'], $request->user()?->id);

        return back()->with('success', 'Invoice line voided successfully.');
    }
}
