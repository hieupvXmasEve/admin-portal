<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use DomainException;
use Illuminate\Support\Facades\DB;

class CloseKnownLegacyDataExceptionsAction
{
    public const LEGITIMATE_STUDENT_CODE = 'AUH113129';

    public const LEGITIMATE_CHARGE_ID = 1184;

    public const HISTORICAL_VOID_CHARGE_ID = 1176;

    public const FIXTURE_STUDENT_ID = 675;

    public const FIXTURE_STUDENT_CODE = 'STU69150356';

    public function __construct(private readonly BillingAccountProvisioner $billingAccountProvisioner) {}

    public static function run(array $data = []): array
    {
        return app(self::class)->handle();
    }

    private function handle(): array
    {
        return DB::transaction(function (): array {
            $totalsBefore = $this->totals();
            $this->attachLegitimateCharge();
            $this->assertHistoricalVoid();
            if ($this->deleteApprovedFixture()) {
                $this->assertReconciliation($totalsBefore);
            }

            return ['closed' => true];
        });
    }

    private function attachLegitimateCharge(): void
    {
        $charge = FinanceCharge::query()->whereKey(self::LEGITIMATE_CHARGE_ID)->lockForUpdate()->firstOrFail();
        $student = Student::query()->whereKey($charge->student_id)->lockForUpdate()->firstOrFail();

        if ($student->student_id !== self::LEGITIMATE_STUDENT_CODE || $charge->status !== FinanceCharge::STATUS_ACTIVE || (float) $charge->amount !== 3_000_000.0 || $charge->charge_type !== FinanceCharge::TYPE_RETAKE_FEE) {
            throw new DomainException('Charge #1184 no longer matches the approved legitimate-retake identity.');
        }

        $ref = FinanceOwnedObligationSource::legacyRetakeFeeChargeRef($charge->id);
        $obligation = FinanceObligation::query()->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)->where('source_kind', 'legacy_course_registration')->where('source_ref', $ref)->where('obligation_type', FinanceCharge::TYPE_RETAKE_FEE)->lockForUpdate()->first();
        if (! $obligation instanceof FinanceObligation) {
            $obligation = FinanceObligation::query()->create(['billing_account_id' => $this->billingAccountProvisioner->forStudent($student->id)->id, 'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM, 'source_kind' => 'legacy_course_registration', 'source_ref' => $ref, 'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE, 'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => $charge->amount, 'currency' => 'VND', 'pricing_rule_version' => 'retake_fee:legacy_backfill', 'pricing_snapshot' => ['provenance' => 'legacy_backfill', 'legacy_charge_id' => $charge->id, 'legacy_charge_amount' => (float) $charge->amount, 'legacy_charge_status' => $charge->status], 'accepted_at' => $charge->created_at ?? now()]);
        }
        if ((float) $obligation->amount !== (float) $charge->amount || $obligation->currency !== 'VND' || $obligation->lifecycle_status !== FinanceObligation::STATUS_ACCEPTED || $obligation->pricing_rule_version !== 'retake_fee:legacy_backfill' || ($obligation->pricing_snapshot['legacy_charge_id'] ?? null) !== $charge->id || ($charge->finance_obligation_id !== null && (int) $charge->finance_obligation_id !== $obligation->id) || $obligation->billingAccount?->student_id !== $student->id) {
            throw new DomainException('Charge #1184 canonical obligation is inconsistent.');
        }
        $charge->update(['finance_obligation_id' => $obligation->id]);
    }

    private function assertHistoricalVoid(): void
    {
        $charge = FinanceCharge::query()->whereKey(self::HISTORICAL_VOID_CHARGE_ID)->lockForUpdate()->firstOrFail();
        if ($charge->status !== FinanceCharge::STATUS_VOID || (float) $charge->amount !== 3_000_000.0) {
            throw new DomainException('Charge #1176 no longer matches the approved historical void.');
        }
    }

    private function deleteApprovedFixture(): bool
    {
        $student = Student::query()->whereKey(self::FIXTURE_STUDENT_ID)->lockForUpdate()->first();
        if ($student === null) {
            return false;
        }
        if ($student->student_id !== self::FIXTURE_STUDENT_CODE || $student->user_id !== null) {
            throw new DomainException('Fixture student identity does not match the approved record.');
        }
        $charge = FinanceCharge::query()->whereKey(1188)->where('student_id', $student->id)->where('semester_id', 8)->where('amount', 300_000)->where('status', FinanceCharge::STATUS_ACTIVE)->lockForUpdate()->first();
        $account = BillingAccount::query()->whereKey(236)->where('student_id', $student->id)->lockForUpdate()->first();
        $invoice = StudentInvoice::query()->whereKey(1471)->where('student_id', $student->id)->where('semester_id', 8)->where('status', 'draft')->where('cached_total_amount', 300_000)->where('cached_paid_amount', 0)->lockForUpdate()->first();
        $line = InvoiceLine::query()->whereKey(1203)->where('invoice_id', 1471)->where('charge_id', 1188)->where('amount_snapshot', 300_000)->where('status', 'active')->lockForUpdate()->first();
        $payment = Payment::query()->whereKey(526)->where('student_id', $student->id)->where('amount', 100_000)->where('method', Payment::METHOD_CASH)->where('status', Payment::STATUS_COMPLETED)->lockForUpdate()->first();
        $application = PaymentApplication::query()->whereKey(774)->where('payment_id', 526)->where('invoice_line_id', 1203)->where('amount', 100_000)->lockForUpdate()->first();
        if (! $charge || ! $account || ! $invoice || ! $line || ! $payment || ! $application || FinanceCharge::query()->where('student_id', $student->id)->count() !== 1 || StudentInvoice::query()->where('student_id', $student->id)->count() !== 1 || Payment::query()->where('student_id', $student->id)->count() !== 1 || BillingAccount::query()->where('student_id', $student->id)->count() !== 1) {
            throw new DomainException('Fixture graph no longer exactly matches the approved deletion scope.');
        }
        if (DB::table('finance_obligations')->where('billing_account_id', $account->id)->exists() || DB::table('finance_credit_entitlements')->where('billing_account_id', $account->id)->exists() || DB::table('finance_discount_entitlements')->where('billing_account_id', $account->id)->exists() || DB::table('payment_surplus_dispositions')->where('payment_id', $payment->id)->exists() || DB::table('discount_allocations')->where('invoice_line_id', $line->id)->exists() || DB::table('credit_applications')->where('invoice_line_id', $line->id)->exists()) {
            throw new DomainException('Fixture graph has unapproved Finance descendants.');
        }
        $application->delete();
        $payment->delete();
        $line->delete();
        $invoice->delete();
        $charge->delete();
        $account->delete();
        DB::table('students')->where('id', $student->id)->delete();

        return true;
    }

    /** @return array{gross:float,invoices:float,payments:float} */
    private function totals(): array
    {
        return [
            'gross' => (float) DB::table('finance_charges')->where('status', FinanceCharge::STATUS_ACTIVE)->sum('amount'),
            'invoices' => (float) DB::table('student_invoices')->sum('cached_total_amount'),
            'payments' => (float) DB::table('payments')->sum('amount'),
        ];
    }

    /** @param array{gross:float,invoices:float,payments:float} $before */
    private function assertReconciliation(array $before): void
    {
        $after = $this->totals();
        if (abs(($before['gross'] - $after['gross']) - 300_000) > 0.009 || abs(($before['invoices'] - $after['invoices']) - 300_000) > 0.009 || abs(($before['payments'] - $after['payments']) - 100_000) > 0.009) {
            throw new DomainException('Approved fixture totals did not reconcile after deletion.');
        }
    }
}
