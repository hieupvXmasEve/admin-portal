<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\QueryException;

/**
 * Finance-owned payer identity provisioning (ADR-0029).
 *
 * Sources never pass billing_account_id — Finance resolves/provisions it from
 * a Student (or, later, an Applicant). Idempotent: one billing account per student.
 */
class BillingAccountProvisioner
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function forStudent(int $studentId): BillingAccount
    {
        if ($this->studentReferences->find($studentId) === null) {
            throw new \RuntimeException("Student reference #{$studentId} cannot be resolved for Finance billing-account provisioning.");
        }

        try {
            return BillingAccount::query()->firstOrCreate(
                ['student_id' => $studentId],
            );
        } catch (QueryException $e) {
            // Concurrent create raced on unique(student_id) — re-read winner.
            $recovered = BillingAccount::query()
                ->where('student_id', $studentId)
                ->first();

            if ($recovered instanceof BillingAccount) {
                return $recovered;
            }

            throw $e;
        }
    }
}
