<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinancePricingCatalog;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestFinanceDebitAction
{
    public function __construct(
        private readonly FinancePricingCatalog $pricingCatalog,
        private readonly CreateFinanceChargeAction $createChargeAction,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
    ) {}

    public function handle(FinanceIntakeData $intake): FinanceIntakeResult
    {
        return DB::transaction(function () use ($intake): FinanceIntakeResult {
            $this->assertFactsCarryNoPricing($intake);
            $this->assertFactsCarryNoPayerIdentity($intake);

            $obligation = $this->findExistingObligation($intake);

            if (! $obligation instanceof FinanceObligation) {
                $obligation = $this->createAcceptedObligation($intake);
            }

            $charge = $this->ensureCharge($obligation, $intake);
            $line = $charge->invoiceLines()->first();

            if (! $line instanceof InvoiceLine) {
                throw new RuntimeException("Finance obligation {$obligation->id} has no invoice line.");
            }

            return new FinanceIntakeResult(
                lifecycle_status: $obligation->lifecycle_status,
                amount: (float) $obligation->amount,
                currency: $obligation->currency,
                pricing_rule_version: $obligation->pricing_rule_version,
                finance_obligation_id: $obligation->id,
                finance_charge_id: $charge->id,
                invoice_line_id: $line->id,
            );
        });
    }

    private function assertFactsCarryNoPricing(FinanceIntakeData $intake): void
    {
        // Finance always stamps pricing_rule_version — sources never supply it.
        if (array_key_exists('pricing_rule_version', $intake->facts)) {
            throw InvalidFinanceIntakePayload::forbiddenPricingFact('pricing_rule_version');
        }

        // Catalog-priced types reject amount/currency (ADR-0026). Staff-supplied and
        // generator-amount strategies accept amount as the Finance-owned price input.
        if ($this->pricingCatalog->allowsAmountInFacts($intake->obligation_type)) {
            return;
        }

        foreach (['amount', 'currency'] as $forbiddenFact) {
            if (array_key_exists($forbiddenFact, $intake->facts)) {
                throw InvalidFinanceIntakePayload::forbiddenPricingFact($forbiddenFact);
            }
        }
    }

    private function assertFactsCarryNoPayerIdentity(FinanceIntakeData $intake): void
    {
        if (array_key_exists('billing_account_id', $intake->facts)) {
            throw InvalidFinanceIntakePayload::forbiddenPayerFact('billing_account_id');
        }
    }

    private function findExistingObligation(FinanceIntakeData $intake): ?FinanceObligation
    {
        return FinanceObligation::query()
            ->where('source_system', $intake->source_system)
            ->where('source_kind', $intake->source_kind)
            ->where('source_ref', $intake->source_ref)
            ->where('obligation_type', $intake->obligation_type)
            ->lockForUpdate()
            ->first();
    }

    private function createAcceptedObligation(FinanceIntakeData $intake): FinanceObligation
    {
        $priced = $this->pricingCatalog->price($intake);
        $billingAccount = $this->resolveBillingAccount($intake);

        return FinanceObligation::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => $intake->source_system,
            'source_kind' => $intake->source_kind,
            'source_ref' => $intake->source_ref,
            'obligation_type' => $intake->obligation_type,
            'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
            'amount' => $priced['amount'],
            'currency' => $priced['currency'],
            'pricing_rule_version' => $priced['rule_version'],
            'pricing_snapshot' => $priced['snapshot'],
            'accepted_at' => now(),
        ]);
    }

    private function ensureCharge(FinanceObligation $obligation, FinanceIntakeData $intake): FinanceCharge
    {
        $existing = $obligation->financeCharge()->with('invoiceLines')->first();

        if ($existing instanceof FinanceCharge) {
            // FIN-05: a voided projection must not block regeneration. Detach the
            // voided row so a fresh materialization can bind to the same obligation.
            if ($existing->status === FinanceCharge::STATUS_VOID) {
                $existing->forceFill(['finance_obligation_id' => null])->save();
            } else {
                return $existing;
            }
        }

        $payload = [
            'finance_obligation_id' => $obligation->id,
            'student_id' => $this->resolveStudentIdForMaterialization($obligation, $intake),
            'semester_id' => $this->requiredIntFact($intake, 'semester_id'),
            'charge_type' => $obligation->obligation_type,
            'amount' => (float) $obligation->amount,
            'description' => $this->description($intake),
            'created_by_user_id' => auth()->id(),
        ];

        if (isset($intake->facts['effective_at']) && is_string($intake->facts['effective_at']) && $intake->facts['effective_at'] !== '') {
            $payload['effective_at'] = $intake->facts['effective_at'];
        }

        if (isset($intake->facts['due_date']) && is_string($intake->facts['due_date']) && $intake->facts['due_date'] !== '') {
            $payload['due_date'] = $intake->facts['due_date'];
        }

        if (isset($intake->facts['invoice_id']) && is_numeric($intake->facts['invoice_id'])) {
            $payload['invoice_id'] = (int) $intake->facts['invoice_id'];
        }

        return $this->createChargeAction->handle($payload);
    }

    /**
     * Resolve the student-keyed ledger projection target from the obligation's
     * billing account (ADR-0029). Sources still send student_id so Finance can
     * provision/resolve the account; they never send billing_account_id.
     *
     * Pre-student accounts (student_id null) must not materialize into the
     * student-keyed collection ledger until Approve links a Student.
     */
    private function resolveStudentIdForMaterialization(
        FinanceObligation $obligation,
        FinanceIntakeData $intake,
    ): int {
        $obligation->loadMissing('billingAccount');

        $account = $obligation->billingAccount;

        if ($account instanceof BillingAccount) {
            if ($account->student_id === null) {
                throw new RuntimeException(
                    "Cannot materialize finance obligation {$obligation->id}: billing account is not linked to a Student."
                );
            }

            return (int) $account->student_id;
        }

        // Transition path: obligation created before payer-key backfill.
        $studentId = $this->requiredIntFact($intake, 'student_id');
        $account = $this->billingAccountProvisioner->forStudent($studentId);
        $obligation->update(['billing_account_id' => $account->id]);

        return (int) $account->student_id;
    }

    private function resolveBillingAccount(FinanceIntakeData $intake): BillingAccount
    {
        $studentId = $this->requiredIntFact($intake, 'student_id');

        return $this->billingAccountProvisioner->forStudent($studentId);
    }

    private function requiredIntFact(FinanceIntakeData $intake, string $key): int
    {
        if (! array_key_exists($key, $intake->facts)) {
            throw InvalidFinanceIntakePayload::missingFact($key);
        }

        return (int) $intake->facts[$key];
    }

    private function description(FinanceIntakeData $intake): string
    {
        $description = $intake->facts['description'] ?? null;

        if (is_string($description) && $description !== '') {
            return $description;
        }

        return "{$intake->obligation_type}: {$intake->source_system}/{$intake->source_kind}/{$intake->source_ref}";
    }
}
