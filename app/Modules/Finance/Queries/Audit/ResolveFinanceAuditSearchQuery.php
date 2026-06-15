<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Audit;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;

/**
 * Deterministic, campus-scoped resolver for the audit workspace universal search.
 *
 * Precedence (locked in design.md):
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
            ->whereHas('student', fn ($s) => $s->where('campus_id', $campusId))
            ->first();
        if ($invoice !== null) {
            return $this->single('invoice', (int) $invoice->id);
        }

        // 3. DNG formatted identifiers, in order.
        foreach (['item_id', 'dng_payment_id', 'dng_transaction_id'] as $field) {
            $dng = DngPaymentRequest::query()
                ->where($field, $q)
                ->whereHas('student', fn ($s) => $s->where('campus_id', $campusId))
                ->first();
            if ($dng !== null) {
                return $this->single('dng', (int) $dng->id);
            }
        }

        // 4. Exact student code (MSSV).
        $student = Student::query()
            ->where('student_id', $q)
            ->where('campus_id', $campusId)
            ->first();
        if ($student !== null) {
            return $this->single('student', (int) $student->id);
        }

        // 5. Payment external_ref — only when the input is not MSSV-shaped, so a
        //    valid student code can never be shadowed by a colliding external_ref.
        if (! $this->looksLikeStudentCode($q)) {
            $payment = Payment::query()
                ->where('external_ref', $q)
                ->whereHas('student', fn ($s) => $s->where('campus_id', $campusId))
                ->first();
            if ($payment !== null) {
                return $this->single('payment', (int) $payment->id);
            }
        }

        // 6. Fuzzy student code/name/email -> single or ambiguous.
        $students = Student::query()
            ->where('campus_id', $campusId)
            ->where(fn ($w) => $w
                ->where('student_id', 'like', "%{$q}%")
                ->orWhere('full_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
            ->orderBy('full_name')
            ->limit(10)
            ->get();

        if ($students->isEmpty()) {
            return $this->empty();
        }

        if ($students->count() === 1) {
            return $this->single('student', (int) $students->first()->id);
        }

        return [
            'status' => 'ambiguous',
            'target' => null,
            'matches' => $students->map(fn (Student $s) => $this->studentMatch($s))->all(),
        ];
    }

    /** @return Resolution */
    private function resolvePrefixed(string $type, int $id, ?int $campusId): array
    {
        $campusScoped = fn ($s) => $s->where('campus_id', $campusId);

        $exists = match ($type) {
            'payment' => Payment::query()->whereKey($id)->whereHas('student', $campusScoped)->exists(),
            'invoice' => StudentInvoice::query()->whereKey($id)->whereHas('student', $campusScoped)->exists(),
            'dng' => DngPaymentRequest::query()->whereKey($id)->whereHas('student', $campusScoped)->exists(),
            'charge' => FinanceCharge::query()->whereKey($id)->whereHas('student', $campusScoped)->exists(),
            default => false,
        };

        return $exists ? $this->single($type, $id) : $this->empty();
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
    private function studentMatch(Student $student): array
    {
        return [
            'type' => 'student',
            'id' => (int) $student->id,
            'label' => (string) $student->full_name,
            'sublabel' => (string) $student->student_id,
        ];
    }
}
