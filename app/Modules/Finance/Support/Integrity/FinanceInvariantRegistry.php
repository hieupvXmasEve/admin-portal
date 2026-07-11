<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

/**
 * Single source of truth for the finance integrity invariants (INV-1..INV-16).
 *
 * Each invariant's SQL is the exact query used by the `finance:audit-invariants`
 * command, with a `{scope}` token spliced into the innermost WHERE so the same
 * definition can run globally (`{scope}` => `1=1`) or scoped to a set of student
 * ids (`{scope}` => `studentScopePredicate` with `{ids}` filled). The command and
 * the workspace consume this registry so their integrity truth cannot drift.
 */
class FinanceInvariantRegistry
{
    /** @return list<FinanceInvariant> */
    public function all(): array
    {
        return [
            new FinanceInvariant(
                'INV-1',
                'CRITICAL',
                'Payment over-allocated (SUM applications > payment.amount)',
                'SELECT COUNT(*) c FROM (
                    SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    WHERE {scope}
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01
                ) t',
                'SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    WHERE {scope}
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01 LIMIT 5',
                'p.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-2',
                'HIGH',
                'student_invoices.cached_paid_amount cache drifts from live (active lines)',
                'SELECT COUNT(*) c FROM (
                    SELECT si.id FROM student_invoices si
                    LEFT JOIN invoice_lines il ON il.invoice_id = si.id AND il.status = "active"
                    LEFT JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE {scope}
                    GROUP BY si.id, si.cached_paid_amount
                    HAVING ABS(si.cached_paid_amount - COALESCE(SUM(pa.amount),0)) > 0.01
                ) t',
                'SELECT si.id FROM student_invoices si
                    LEFT JOIN invoice_lines il ON il.invoice_id = si.id AND il.status = "active"
                    LEFT JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE {scope}
                    GROUP BY si.id, si.cached_paid_amount
                    HAVING ABS(si.cached_paid_amount - COALESCE(SUM(pa.amount),0)) > 0.01 LIMIT 5',
                'si.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-3',
                'CRITICAL',
                'Active positive charge NOT linked to exactly 1 active invoice_line',
                'SELECT COUNT(*) c FROM (
                    SELECT fc.id FROM finance_charges fc
                    LEFT JOIN invoice_lines il ON il.charge_id = fc.id AND il.status = "active"
                    WHERE fc.status = "active" AND fc.amount > 0 AND ({scope})
                    GROUP BY fc.id HAVING COUNT(il.id) <> 1
                ) t',
                'SELECT fc.id FROM finance_charges fc
                    LEFT JOIN invoice_lines il ON il.charge_id = fc.id AND il.status = "active"
                    WHERE fc.status = "active" AND fc.amount > 0 AND ({scope})
                    GROUP BY fc.id HAVING COUNT(il.id) <> 1 LIMIT 5',
                'fc.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-4',
                'CRITICAL',
                'Payment still applied to a VOID invoice_line (not reversed)',
                'SELECT COUNT(*) c FROM (
                    SELECT il.id FROM invoice_lines il
                    JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE il.status = "void" AND ({scope}) GROUP BY il.id HAVING SUM(pa.amount) > 0.01
                ) t',
                'SELECT il.id FROM invoice_lines il
                    JOIN payment_applications pa ON pa.invoice_line_id = il.id
                    WHERE il.status = "void" AND ({scope}) GROUP BY il.id HAVING SUM(pa.amount) > 0.01 LIMIT 5',
                'il.invoice_id IN (SELECT id FROM student_invoices WHERE student_id IN ({ids}))',
            ),
            new FinanceInvariant(
                'INV-5',
                'CRITICAL',
                'Discount status=reversed but allocations still net > 0',
                'SELECT COUNT(*) c FROM (
                    SELECT idc.id FROM invoice_discounts idc
                    JOIN discount_allocations da ON da.invoice_discount_id = idc.id
                    WHERE idc.status = "reversed" AND ({scope}) GROUP BY idc.id HAVING SUM(da.amount) > 0.01
                ) t',
                'SELECT idc.id FROM invoice_discounts idc
                    JOIN discount_allocations da ON da.invoice_discount_id = idc.id
                    WHERE idc.status = "reversed" AND ({scope}) GROUP BY idc.id HAVING SUM(da.amount) > 0.01 LIMIT 5',
                'idc.invoice_id IN (SELECT id FROM student_invoices WHERE student_id IN ({ids}))',
            ),
            new FinanceInvariant(
                'INV-6',
                'CRITICAL',
                'Duplicate invoice for same (student_id, semester_id)',
                'SELECT COUNT(*) c FROM (
                    SELECT student_id, semester_id FROM student_invoices
                    WHERE {scope}
                    GROUP BY student_id, semester_id HAVING COUNT(*) > 1
                ) t',
                'SELECT MIN(id) id FROM student_invoices
                    WHERE {scope}
                    GROUP BY student_id, semester_id HAVING COUNT(*) > 1 LIMIT 5',
                'student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-7',
                'HIGH',
                'Duplicate scholarship award for same student',
                'SELECT COUNT(*) c FROM (
                    SELECT student_id FROM student_scholarship_awards
                    WHERE {scope}
                    GROUP BY student_id HAVING COUNT(*) > 1
                ) t',
                'SELECT student_id id FROM student_scholarship_awards
                    WHERE {scope}
                    GROUP BY student_id HAVING COUNT(*) > 1 LIMIT 5',
                'student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-8',
                'CRITICAL',
                'Negative balance (discount+cash+credit > charge) on active positive charge',
                'SELECT COUNT(*) c FROM finance_charges fc
                    WHERE fc.status = "active" AND fc.amount > 0 AND (
                        fc.amount
                        - COALESCE((SELECT SUM(pa.amount) FROM invoice_lines il
                            JOIN payment_applications pa ON pa.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(da.amount) FROM invoice_lines il
                            JOIN discount_allocations da ON da.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(ca.amount) FROM invoice_lines il
                            JOIN credit_applications ca ON ca.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                    ) < -0.01 AND ({scope})',
                'SELECT fc.id FROM finance_charges fc
                    WHERE fc.status = "active" AND fc.amount > 0 AND (
                        fc.amount
                        - COALESCE((SELECT SUM(pa.amount) FROM invoice_lines il
                            JOIN payment_applications pa ON pa.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(da.amount) FROM invoice_lines il
                            JOIN discount_allocations da ON da.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                        - COALESCE((SELECT SUM(ca.amount) FROM invoice_lines il
                            JOIN credit_applications ca ON ca.invoice_line_id = il.id
                            WHERE il.charge_id = fc.id AND il.status = "active"),0)
                    ) < -0.01 AND ({scope}) LIMIT 5',
                'fc.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-9',
                'HIGH',
                'Duplicate invoice_number',
                'SELECT COUNT(*) c FROM (
                    SELECT invoice_number FROM student_invoices
                    WHERE {scope}
                    GROUP BY invoice_number HAVING COUNT(*) > 1
                ) t',
                'SELECT MIN(id) id FROM student_invoices
                    WHERE {scope}
                    GROUP BY invoice_number HAVING COUNT(*) > 1 LIMIT 5',
                'student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-10',
                'HIGH',
                'Payment with non-positive amount',
                'SELECT COUNT(*) c FROM payments WHERE amount <= 0 AND ({scope})',
                'SELECT id FROM payments WHERE amount <= 0 AND ({scope}) LIMIT 5',
                'student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-11',
                'HIGH',
                'duplicate webhook payload_hash (idempotency loss)',
                'SELECT COUNT(*) c FROM (
                    SELECT payload_hash FROM dng_webhook_events
                    WHERE {scope}
                    GROUP BY payload_hash HAVING COUNT(*) > 1
                ) t',
                'SELECT MIN(id) id FROM dng_webhook_events
                    WHERE {scope}
                    GROUP BY payload_hash HAVING COUNT(*) > 1 LIMIT 5',
                'dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE student_id IN ({ids}))',
            ),
            new FinanceInvariant(
                'INV-12',
                'HIGH',
                'Bridged DNG request amount diverges from its canonical payment (rail mismatch)',
                'SELECT COUNT(*) c FROM dng_payment_requests dpr
                    JOIN payments p ON p.id = dpr.payment_id
                    WHERE dpr.payment_id IS NOT NULL
                      AND ABS(dpr.amount - p.amount) > 0.01 AND ({scope})',
                'SELECT dpr.id FROM dng_payment_requests dpr
                    JOIN payments p ON p.id = dpr.payment_id
                    WHERE dpr.payment_id IS NOT NULL
                      AND ABS(dpr.amount - p.amount) > 0.01 AND ({scope}) LIMIT 5',
                'dpr.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-13',
                'HIGH',
                'Live installment (pending/awaiting_payment) on a voided charge',
                'SELECT COUNT(*) c FROM (
                    SELECT fc.id FROM finance_charges fc
                    JOIN finance_charge_installments fci ON fci.finance_charge_id = fc.id
                    WHERE fc.status = "void"
                      AND fci.status IN ("pending", "awaiting_payment") AND ({scope})
                    GROUP BY fc.id
                ) t',
                'SELECT fc.id FROM finance_charges fc
                    JOIN finance_charge_installments fci ON fci.finance_charge_id = fc.id
                    WHERE fc.status = "void"
                      AND fci.status IN ("pending", "awaiting_payment") AND ({scope})
                    GROUP BY fc.id LIMIT 5',
                'fc.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-14',
                'HIGH',
                'DNG request amount diverges from sum of its pivot rows',
                'SELECT COUNT(*) c FROM (
                    SELECT dpr.id FROM dng_payment_requests dpr
                    JOIN dng_payment_request_charges dprc ON dprc.dng_payment_request_id = dpr.id
                    WHERE {scope}
                    GROUP BY dpr.id, dpr.amount
                    HAVING ABS(dpr.amount - SUM(dprc.amount)) > 0.01
                ) t',
                'SELECT dpr.id FROM dng_payment_requests dpr
                    JOIN dng_payment_request_charges dprc ON dprc.dng_payment_request_id = dpr.id
                    WHERE {scope}
                    GROUP BY dpr.id, dpr.amount
                    HAVING ABS(dpr.amount - SUM(dprc.amount)) > 0.01 LIMIT 5',
                'dpr.student_id IN ({ids})',
            ),
            new FinanceInvariant(
                'INV-15',
                'HIGH',
                'DNG pivot amount diverges from its linked installment amount',
                'SELECT COUNT(*) c FROM dng_payment_request_charges dprc
                    JOIN finance_charge_installments fci ON fci.id = dprc.finance_charge_installment_id
                    WHERE dprc.finance_charge_installment_id IS NOT NULL
                      AND ABS(dprc.amount - fci.amount) > 0.01 AND ({scope})',
                'SELECT dprc.id FROM dng_payment_request_charges dprc
                    JOIN finance_charge_installments fci ON fci.id = dprc.finance_charge_installment_id
                    WHERE dprc.finance_charge_installment_id IS NOT NULL
                      AND ABS(dprc.amount - fci.amount) > 0.01 AND ({scope}) LIMIT 5',
                'dprc.dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE student_id IN ({ids}))',
            ),
            // Wave 6 issue 03: zero-row sample had no legacy signed adjustments.
            // If any active negative adjustment appears later, exception-list it
            // (convert to credit entitlement or void) — never new free-signed charges.
            new FinanceInvariant(
                'INV-16',
                'HIGH',
                'Active signed (negative) adjustment charge rows',
                'SELECT COUNT(*) c FROM finance_charges fc
                    WHERE fc.status = "active"
                      AND fc.charge_type = "adjustment"
                      AND fc.amount < 0
                      AND ({scope})',
                'SELECT fc.id FROM finance_charges fc
                    WHERE fc.status = "active"
                      AND fc.charge_type = "adjustment"
                      AND fc.amount < 0
                      AND ({scope}) LIMIT 5',
                'fc.student_id IN ({ids})',
            ),
        ];
    }

    public function find(string $code): ?FinanceInvariant
    {
        foreach ($this->all() as $invariant) {
            if ($invariant->code === $code) {
                return $invariant;
            }
        }

        return null;
    }
}
