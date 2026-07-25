<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Audit;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

/**
 * Deterministic, campus-scoped resolver for the audit workspace universal search.
 *
 * Search precedence is an executable product contract:
 *   1. Explicit prefixes payment:/invoice:/dng:/charge: (always win)
 *   2. Exact student_invoices.invoice_number
 *   3. DNG ids: item_id, then dng_payment_id, then dng_transaction_id
 *   4. Exact students.student_id (MSSV)
 *   5. payment.external_ref — only when the input is not MSSV-shaped
 *   6. Student code/name/email fuzzy (may be ambiguous)
 *
 * Bare integers never guess a primary key. Every candidate is filtered to the
 * owning student's campus; cross-campus rows are invisible (treated as no-match,
 * never "denied").
 *
 * @phpstan-type Resolution array{status:string, target:array{type:string,id:int}|null, matches:list<array{type:string,id:int,label:string,sublabel:string}>}
 */
class ResolveFinanceAuditSearchQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    /** @return Resolution */
    public function handle(?string $q, ?int $campusId): array
    {
        $q = trim((string) ($q ?? ''));
        if ($q === '' || $campusId === null) {
            // No search term, or no campus context — resolve nothing. Defense in
            // depth: every branch is campus-filtered, so a null campus would match
            // nothing anyway; this just makes the contract explicit and skips work.
            return $this->empty();
        }

        // 1. Explicit prefixes always win.
        if (preg_match('/^(payment|invoice|dng|charge):(\d+)$/', $q, $matches) === 1) {
            return $this->resolvePrefixed($matches[1], (int) $matches[2], $campusId);
        }

        // 2. Exact invoice number.
        $invoice = StudentInvoice::query()
            ->where('invoice_number', $q)
            ->first();
        if ($invoice !== null && $this->visible((int) $invoice->student_id, $campusId)) {
            return $this->single('invoice', (int) $invoice->id);
        }

        // 3. DNG formatted identifiers, in order.
        foreach (['item_id', 'dng_payment_id', 'dng_transaction_id'] as $field) {
            $dng = DngPaymentRequest::query()
                ->where($field, $q)
                ->first();
            if ($dng !== null && $this->visible((int) $dng->student_id, $campusId)) {
                return $this->single('dng', (int) $dng->id);
            }
        }

        // 4. Exact student code (MSSV).
        $student = $this->studentReferences->findByStudentCode($q, $campusId);
        if ($student !== null) {
            return $this->single('student', (int) $student->id);
        }

        // 5. Payment external_ref — only when the input is not MSSV-shaped, so a
        //    valid student code can never be shadowed by a colliding external_ref.
        if (! $this->looksLikeStudentCode($q)) {
            $payment = Payment::query()
                ->where('external_ref', $q)
                ->first();
            if ($payment !== null && $this->visible((int) $payment->student_id, $campusId)) {
                return $this->single('payment', (int) $payment->id);
            }
        }

        // 6. Fuzzy student code/name/email -> single or ambiguous.
        $students = $this->studentReferences->search($q, $campusId);

        if ($students === []) {
            return $this->empty();
        }

        if (count($students) === 1) {
            return $this->single('student', $students[0]->id);
        }

        return [
            'status' => 'ambiguous',
            'target' => null,
            'matches' => array_map(fn (StudentReference $reference): array => $this->studentMatch($reference), $students),
        ];
    }

    /** @return Resolution */
    private function resolvePrefixed(string $type, int $id, ?int $campusId): array
    {
        $studentId = match ($type) {
            'payment' => Payment::query()->find($id)?->student_id,
            'invoice' => StudentInvoice::query()->find($id)?->student_id,
            'dng' => DngPaymentRequest::query()->find($id)?->student_id,
            'charge' => FinanceCharge::query()->find($id)?->student_id,
            default => null,
        };

        return $studentId !== null && $this->visible((int) $studentId, $campusId)
            ? $this->single($type, $id)
            : $this->empty();
    }

    private function looksLikeStudentCode(string $q): bool
    {
        // MSSV format: letter prefix + digits, e.g. AUH115952, SWB123, STU12345678.
        return preg_match('/^[A-Za-z]{2,}\d{2,}$/', $q) === 1;
    }

    /** @return Resolution */
    private function single(string $type, int $id): array
    {
        return ['status' => 'single', 'target' => ['type' => $type, 'id' => $id], 'matches' => []];
    }

    /** @return Resolution */
    private function empty(): array
    {
        return ['status' => 'empty', 'target' => null, 'matches' => []];
    }

    /** @return array{type:string,id:int,label:string,sublabel:string} */
    private function visible(int $studentId, int $campusId): bool
    {
        return $this->studentReferences->find($studentId)?->campusId === $campusId;
    }

    private function studentMatch(StudentReference $student): array
    {
        return [
            'type' => 'student',
            'id' => $student->id,
            'label' => $student->fullName,
            'sublabel' => $student->studentCode,
        ];
    }
}
