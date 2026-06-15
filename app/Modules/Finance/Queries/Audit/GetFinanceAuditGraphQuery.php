<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Audit;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Collection;

/**
 * Builds the student-scoped money graph for the audit workspace: nodes + edges,
 * a cache-vs-derived balance panel (via SettlementService), and the raw signed
 * ledger entries the timeline builder consumes. No new money math — derived
 * numbers come from SettlementService only.
 *
 * Scope is bounded: when no semester is given it defaults to the student's most
 * recent semester with finance activity (never an unbounded all-history scan).
 */
class GetFinanceAuditGraphQuery
{
    private const DRIFT_TOLERANCE = 0.01;

    public function __construct(private readonly SettlementService $settlement) {}

    /**
     * @param  array{type:string,id:int}  $target
     * @return array{subject:array,student_id:int,nodes:list<array>,edges:list<array>,derived_balance:list<array>,ledger_entries:list<array>}
     */
    public function handle(array $target, ?int $semesterId = null, ?int $billingCycleId = null): array
    {
        $studentId = $this->resolveStudentId($target);
        $student = Student::find($studentId);

        if ($student === null) {
            return $this->emptyGraph($studentId);
        }

        $semesterIds = $this->scopeSemesterIds($studentId, $semesterId);

        $invoices = $student->invoices()
            ->whereIn('semester_id', $semesterIds)
            ->when($billingCycleId !== null, fn ($q) => $q->where('billing_cycle_id', $billingCycleId))
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->get();

        $lines = $invoices->flatMap(fn (StudentInvoice $invoice) => $invoice->invoiceLines);
        $inScopeLineIds = $lines->pluck('id')->map(fn ($id) => (int) $id)->all();

        $payments = $student->payments()->with('applications')->get();
        $dngRequests = $student->dngPaymentRequests()->with('chargeLinks')->get();
        $charges = $this->collectCharges($student, $semesterIds, $lines, $dngRequests);

        $nodes = $this->buildNodes($student, $invoices, $lines, $charges, $payments, $dngRequests);
        $edges = $this->buildEdges($invoices, $lines, $charges, $payments, $dngRequests, $inScopeLineIds);

        return [
            'subject' => $this->subject($student),
            'student_id' => (int) $student->id,
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'derived_balance' => $this->derivedBalance($invoices),
            'ledger_entries' => $this->ledgerEntries($lines),
        ];
    }

    private function resolveStudentId(array $target): int
    {
        $type = (string) ($target['type'] ?? '');
        $id = (int) ($target['id'] ?? 0);

        return match ($type) {
            'student' => $id,
            'invoice' => (int) (StudentInvoice::find($id)?->student_id ?? 0),
            'payment' => (int) (Payment::find($id)?->student_id ?? 0),
            'charge' => (int) (FinanceCharge::find($id)?->student_id ?? 0),
            'dng' => (int) (DngPaymentRequest::find($id)?->student_id ?? 0),
            default => 0,
        };
    }

    /** @return list<int> */
    private function scopeSemesterIds(int $studentId, ?int $semesterId): array
    {
        if ($semesterId !== null) {
            return [$semesterId];
        }

        $semesterIds = StudentInvoice::where('student_id', $studentId)->pluck('semester_id')
            ->merge(FinanceCharge::where('student_id', $studentId)->pluck('semester_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $semesterIds->isEmpty() ? [] : [(int) $semesterIds->max()];
    }

    /** @return Collection<int,FinanceCharge> keyed by id */
    private function collectCharges(Student $student, array $semesterIds, Collection $lines, Collection $dngRequests): Collection
    {
        $scoped = $student->financeCharges()->whereIn('semester_id', $semesterIds)->get();
        $fromLines = $lines->pluck('charge')->filter();

        $known = $scoped->pluck('id')->merge($fromLines->pluck('id'))->unique();
        $dngChargeIds = $dngRequests
            ->flatMap(fn (DngPaymentRequest $dng) => $dng->chargeLinks->pluck('finance_charge_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $missing = FinanceCharge::whereIn('id', $dngChargeIds->diff($known)->values())->get();

        return $scoped->concat($fromLines)->concat($missing)->unique('id')->keyBy('id');
    }

    /** @return array<string,array> keyed by node key */
    private function buildNodes(Student $student, Collection $invoices, Collection $lines, Collection $charges, Collection $payments, Collection $dngRequests): array
    {
        $nodes = [];
        $put = function (array $node) use (&$nodes): void {
            $nodes[$node['key']] = $node;
        };

        $put($this->node('student', (int) $student->id, (string) $student->full_name, $student->academic_status));

        foreach ($invoices as $invoice) {
            $put($this->node('invoice', (int) $invoice->id, (string) $invoice->invoice_number, $invoice->status, (float) $invoice->total_amount, $invoice->due_date?->toDateString()));
        }
        foreach ($lines as $line) {
            $put($this->node('invoice_line', (int) $line->id, (string) $line->description_snapshot, $line->status, (float) $line->amount_snapshot));
        }
        foreach ($charges as $charge) {
            $put($this->node('charge', (int) $charge->id, (string) $charge->description, $charge->status, (float) $charge->amount, $charge->effective_at?->toDateString()));
        }
        foreach ($payments as $payment) {
            $put($this->node('payment', (int) $payment->id, (string) ($payment->method ?? $payment->source), $payment->status, (float) $payment->amount, $payment->paid_at?->toDateTimeString()));
        }
        foreach ($dngRequests as $dng) {
            $put($this->node('dng', (int) $dng->id, (string) $dng->item_id, $dng->status, (float) $dng->amount, $dng->created_at?->toDateTimeString()));
        }

        return $nodes;
    }

    /** @return list<array> */
    private function buildEdges(Collection $invoices, Collection $lines, Collection $charges, Collection $payments, Collection $dngRequests, array $inScopeLineIds): array
    {
        $edges = [];

        foreach ($lines as $line) {
            $edges[] = $this->edge("invoice:{$line->invoice_id}", "invoice_line:{$line->id}", 'invoice_line', (float) $line->amount_snapshot);
            if ($line->charge_id !== null && $charges->has((int) $line->charge_id)) {
                $edges[] = $this->edge("invoice_line:{$line->id}", "charge:{$line->charge_id}", 'charge_line', (float) $line->amount_snapshot);
            }
        }

        foreach ($payments as $payment) {
            foreach ($payment->applications as $application) {
                if (in_array((int) $application->invoice_line_id, $inScopeLineIds, true)) {
                    $edges[] = $this->edge("payment:{$payment->id}", "invoice_line:{$application->invoice_line_id}", 'payment_application', (float) $application->amount);
                }
            }
        }

        foreach ($dngRequests as $dng) {
            foreach ($dng->chargeLinks as $link) {
                if ($link->finance_charge_id !== null && $charges->has((int) $link->finance_charge_id)) {
                    $edges[] = $this->edge("dng:{$dng->id}", "charge:{$link->finance_charge_id}", 'dng_charge', (float) $link->amount);
                }
            }
        }

        return $edges;
    }

    /** @return list<array> */
    private function derivedBalance(Collection $invoices): array
    {
        return $invoices->map(function (StudentInvoice $invoice): array {
            $snapshot = $this->settlement->deriveInvoiceSnapshot($invoice);
            $cachedTotal = (float) $invoice->cached_total_amount;
            $cachedPaid = (float) $invoice->cached_paid_amount;

            $drift = abs($cachedPaid - $snapshot['paid']) > self::DRIFT_TOLERANCE
                || abs($cachedTotal - $snapshot['net']) > self::DRIFT_TOLERANCE;

            return [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) $invoice->invoice_number,
                'cached_total_amount' => $cachedTotal,
                'cached_paid_amount' => $cachedPaid,
                'derived_net' => (float) $snapshot['net'],
                'derived_paid' => (float) $snapshot['paid'],
                'drift' => $drift,
            ];
        })->all();
    }

    /** @return list<array> */
    private function ledgerEntries(Collection $lines): array
    {
        $entries = [];

        foreach ($lines as $line) {
            foreach ($line->paymentApplications as $application) {
                $entries[] = [
                    'at' => $application->applied_at?->toDateTimeString(),
                    'type' => 'payment_application',
                    'amount' => (float) $application->amount,
                    'entry_type' => (string) $application->entry_type,
                    'refs' => [
                        'payment' => (int) $application->payment_id,
                        'invoice_line' => (int) $line->id,
                    ],
                ];
            }
            foreach ($line->discountAllocations as $allocation) {
                $entries[] = [
                    'at' => $allocation->created_at?->toDateTimeString(),
                    'type' => 'discount_allocation',
                    'amount' => (float) $allocation->amount,
                    'entry_type' => (string) $allocation->entry_type,
                    'refs' => [
                        'invoice_discount' => (int) $allocation->invoice_discount_id,
                        'invoice_line' => (int) $line->id,
                    ],
                ];
            }
        }

        return $entries;
    }

    private function subject(Student $student): array
    {
        return [
            'student_id' => (int) $student->id,
            'student_code' => (string) $student->student_id,
            'full_name' => (string) $student->full_name,
            'campus_id' => $student->campus_id !== null ? (int) $student->campus_id : null,
        ];
    }

    private function node(string $type, int $id, string $label, ?string $status = null, ?float $amount = null, ?string $date = null): array
    {
        return [
            'key' => "{$type}:{$id}",
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'amount' => $amount,
            'date' => $date,
        ];
    }

    private function edge(string $from, string $to, string $kind, ?float $amount = null): array
    {
        return ['from' => $from, 'to' => $to, 'kind' => $kind, 'amount' => $amount];
    }

    /** @return array{subject:array,student_id:int,nodes:list,edges:list,derived_balance:list,ledger_entries:list} */
    private function emptyGraph(int $studentId): array
    {
        return [
            'subject' => ['student_id' => $studentId, 'student_code' => null, 'full_name' => null, 'campus_id' => null],
            'student_id' => $studentId,
            'nodes' => [],
            'edges' => [],
            'derived_balance' => [],
            'ledger_entries' => [],
        ];
    }
}
