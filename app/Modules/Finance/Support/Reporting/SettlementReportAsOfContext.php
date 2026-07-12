<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use Carbon\CarbonImmutable;

final readonly class SettlementReportAsOfContext
{
    public const MODE_CURRENT = 'current';

    public const MODE_AS_OF = 'as_of';

    /**
     * @param  string  $timezone  IANA timezone used to interpret and display the report boundary.
     */
    private function __construct(
        public string $mode,
        public CarbonImmutable $captured_at,
        public string $timezone,
    ) {}

    public static function current(?string $timezone = null): self
    {
        $timezone ??= (string) config('app.timezone', 'UTC');

        return new self(self::MODE_CURRENT, CarbonImmutable::now($timezone), $timezone);
    }

    public static function fromInput(?string $asOf, ?string $timezone = null): self
    {
        $timezone ??= (string) config('app.timezone', 'UTC');

        if ($asOf === null || trim($asOf) === '') {
            return self::current($timezone);
        }

        return new self(
            self::MODE_AS_OF,
            CarbonImmutable::parse($asOf, $timezone),
            $timezone,
        );
    }

    public function asOf(): ?CarbonImmutable
    {
        return $this->mode === self::MODE_AS_OF ? $this->captured_at : null;
    }

    /**
     * @return array{position_mode:string,as_of_timestamp:string,as_of_timezone:string,business_effective_timestamp_rules:list<string>,legacy_evidence_policy:string,restatement_workflow:string,unapplied_cash_policy:string}
     */
    public function payload(): array
    {
        return [
            'position_mode' => $this->mode,
            'as_of_timestamp' => $this->captured_at->toIso8601String(),
            'as_of_timezone' => $this->timezone,
            'business_effective_timestamp_rules' => [
                'Charges: finance_charges.effective_at; missing values are an as-of integrity issue.',
                'Cash: payments.paid_at and payment_applications.applied_at must both be at or before the boundary.',
                'Discounts: discount_allocations has no business-effective timestamp; as-of reads surface an integrity issue instead of using created_at.',
                'Credit: credit_applications.applied_at and finance_credit_entitlements.approved_at must both be at or before the boundary.',
                'Void: finance_charges.voided_at and invoice_lines.voided_at take effect at their recorded business timestamp.',
                'Surplus: payment_surplus_dispositions.disposed_at controls historical unapplied-cash disposition.',
            ],
            'legacy_evidence_policy' => 'Never infer chronology from created_at; surface stable as-of issue codes or explicitly exclude the evidence.',
            'restatement_workflow' => 'Later payment, reversal, refund, or void events do not rewrite this snapshot; any correction requires an explicit restatement workflow.',
            'unapplied_cash_policy' => 'Historical unapplied cash is excluded until a canonical payment-surplus timeline reader exists; no current-position recomputation is used.',
        ];
    }
}
