<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use Illuminate\Support\Facades\DB;

class BackfillBillingAccountsAction
{
    public function __construct(
        private readonly BillingAccountProvisioner $provisioner,
    ) {}

    /**
     * @return array{
     *     students_checked:int,
     *     students_created:int,
     *     students_already:int,
     *     obligations_checked:int,
     *     obligations_updated:int,
     *     obligations_already:int,
     *     obligations_skipped:int,
     *     details:array<int, array<string, mixed>>
     * }
     */
    public function run(bool $dryRun = false): array
    {
        $summary = [
            'students_checked' => 0,
            'students_created' => 0,
            'students_already' => 0,
            'obligations_checked' => 0,
            'obligations_updated' => 0,
            'obligations_already' => 0,
            'obligations_skipped' => 0,
            'details' => [],
        ];

        $this->backfillStudents($summary, $dryRun);
        $this->backfillObligations($summary, $dryRun);

        return $summary;
    }

    /**
     * @param  array{
     *     students_checked:int,
     *     students_created:int,
     *     students_already:int,
     *     obligations_checked:int,
     *     obligations_updated:int,
     *     obligations_already:int,
     *     obligations_skipped:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function backfillStudents(array &$summary, bool $dryRun): void
    {
        Student::query()
            ->orderBy('id')
            ->chunkById(200, function ($students) use (&$summary, $dryRun): void {
                foreach ($students as $student) {
                    $summary['students_checked']++;

                    $existing = BillingAccount::query()
                        ->where('student_id', $student->id)
                        ->first();

                    if ($existing instanceof BillingAccount) {
                        $summary['students_already']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $this->provisioner->forStudent((int) $student->id);
                    }

                    $summary['students_created']++;
                }
            });
    }

    /**
     * @param  array{
     *     students_checked:int,
     *     students_created:int,
     *     students_already:int,
     *     obligations_checked:int,
     *     obligations_updated:int,
     *     obligations_already:int,
     *     obligations_skipped:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function backfillObligations(array &$summary, bool $dryRun): void
    {
        FinanceObligation::query()
            ->orderBy('id')
            ->chunkById(200, function ($obligations) use (&$summary, $dryRun): void {
                foreach ($obligations as $obligation) {
                    $summary['obligations_checked']++;

                    if ($obligation->billing_account_id !== null) {
                        $summary['obligations_already']++;

                        continue;
                    }

                    $studentId = $this->resolveStudentId($obligation);

                    if ($studentId === null) {
                        $summary['obligations_skipped']++;
                        $summary['details'][] = [
                            'status' => 'skipped',
                            'reason' => 'missing_student_evidence',
                            'finance_obligation_id' => (int) $obligation->id,
                        ];

                        continue;
                    }

                    if ($dryRun) {
                        $summary['obligations_updated']++;

                        continue;
                    }

                    DB::transaction(function () use ($obligation, $studentId): void {
                        $account = $this->provisioner->forStudent($studentId);
                        $obligation->update(['billing_account_id' => $account->id]);
                    });
                    $summary['obligations_updated']++;
                }
            });
    }

    private function resolveStudentId(FinanceObligation $obligation): ?int
    {
        $charge = FinanceCharge::query()
            ->where('finance_obligation_id', $obligation->id)
            ->orderBy('id')
            ->first();

        if ($charge instanceof FinanceCharge && $charge->student_id !== null) {
            return (int) $charge->student_id;
        }

        $snapshot = $obligation->pricing_snapshot;
        if (is_array($snapshot)) {
            if (isset($snapshot['student_id']) && is_numeric($snapshot['student_id'])) {
                return (int) $snapshot['student_id'];
            }

            if (
                isset($snapshot['facts']['student_id'])
                && is_numeric($snapshot['facts']['student_id'])
            ) {
                return (int) $snapshot['facts']['student_id'];
            }
        }

        return null;
    }
}
