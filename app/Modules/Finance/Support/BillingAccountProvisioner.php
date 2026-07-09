<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\BillingAccount;
use Illuminate\Database\QueryException;

/**
 * Finance-owned payer identity provisioning (ADR-0029).
 *
 * Sources never pass billing_account_id — Finance resolves/provisions it from
 * a Student (or, later, an Applicant). Idempotent: one billing account per student.
 */
class BillingAccountProvisioner
{
    public function forStudent(int $studentId): BillingAccount
    {
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
