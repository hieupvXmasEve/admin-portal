<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Modules\Finance\Models\FinanceObligation;
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
    ) {}

    public function handle(FinanceIntakeData $intake): FinanceIntakeResult
    {
        return DB::transaction(function () use ($intake): FinanceIntakeResult {
            $this->assertFactsCarryNoPricing($intake);

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
                finance_obligation_id: $obligation->id,
                finance_charge_id: $charge->id,
                invoice_line_id: $line->id,
                lifecycle_status: $obligation->lifecycle_status,
                amount: (float) $obligation->amount,
                currency: $obligation->currency,
                pricing_rule_version: $obligation->pricing_rule_version,
            );
        });
    }

    private function assertFactsCarryNoPricing(FinanceIntakeData $intake): void
    {
        foreach (['amount', 'currency', 'pricing_rule_version'] as $forbiddenFact) {
            if (array_key_exists($forbiddenFact, $intake->facts)) {
                throw InvalidFinanceIntakePayload::forbiddenPricingFact($forbiddenFact);
            }
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

        return FinanceObligation::query()->create([
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
            return $existing;
        }

        return $this->createChargeAction->handle([
            'finance_obligation_id' => $obligation->id,
            'student_id' => $this->requiredIntFact($intake, 'student_id'),
            'semester_id' => $this->requiredIntFact($intake, 'semester_id'),
            'charge_type' => $obligation->obligation_type,
            'amount' => (float) $obligation->amount,
            'description' => $this->description($intake),
            'created_by_user_id' => auth()->id(),
        ]);
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
