<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\SettlementBypassAllowlist;

/**
 * @return list<array{start:int,end:int}>
 */
function settlementMutationGuardedCallbackRanges(string $contents): array
{
    $tokens = token_get_all($contents);
    $positionedTokens = [];
    $offset = 0;

    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $positionedTokens[] = [
            'id' => is_array($token) ? $token[0] : null,
            'text' => $text,
            'start' => $offset,
        ];
        $offset += strlen($text);
    }

    $ranges = [];
    $tokenCount = count($positionedTokens);
    for ($index = 0; $index < $tokenCount; $index++) {
        if (($positionedTokens[$index]['id'] ?? null) !== T_STRING
            || $positionedTokens[$index]['text'] !== 'settlementMutationGuard') {
            continue;
        }

        $handleIndex = null;
        for ($candidate = $index + 1; $candidate < min($index + 6, $tokenCount); $candidate++) {
            if (($positionedTokens[$candidate]['id'] ?? null) === T_STRING
                && $positionedTokens[$candidate]['text'] === 'handle') {
                $handleIndex = $candidate;
                break;
            }
        }

        if ($handleIndex === null) {
            continue;
        }

        $functionIndex = null;
        for ($candidate = $handleIndex + 1; $candidate < $tokenCount; $candidate++) {
            if (($positionedTokens[$candidate]['id'] ?? null) === T_FUNCTION) {
                $functionIndex = $candidate;
                break;
            }

            if ($positionedTokens[$candidate]['text'] === ';') {
                break;
            }
        }

        if ($functionIndex === null) {
            continue;
        }

        $bodyStartIndex = null;
        for ($candidate = $functionIndex + 1; $candidate < $tokenCount; $candidate++) {
            if ($positionedTokens[$candidate]['text'] === '{') {
                $bodyStartIndex = $candidate;
                break;
            }
        }

        if ($bodyStartIndex === null) {
            continue;
        }

        $depth = 0;
        for ($candidate = $bodyStartIndex; $candidate < $tokenCount; $candidate++) {
            if ($positionedTokens[$candidate]['text'] === '{') {
                $depth++;
            } elseif ($positionedTokens[$candidate]['text'] === '}') {
                $depth--;
                if ($depth === 0) {
                    $ranges[] = [
                        'start' => $positionedTokens[$bodyStartIndex]['start'],
                        'end' => $positionedTokens[$candidate]['start'],
                    ];
                    break;
                }
            }
        }
    }

    return $ranges;
}

/** @param list<array{start:int,end:int}> $ranges */
function settlementMutationOffsetIsGuarded(int $offset, array $ranges): bool
{
    foreach ($ranges as $range) {
        if ($offset > $range['start'] && $offset < $range['end']) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function settlementBypassViolations(string $root): array
{
    $violations = [];
    $allowlist = SettlementBypassAllowlist::entries();
    $patterns = [
        'local settlement formula' => '/(?:amount_snapshot|fc\.amount|finance_charges\.amount|total_amount)\s*-\s*(?:.*(?:paid|payment|discount|credit))/i',
        'direct money-model writer' => '/\b(?:FinanceCharge|InvoiceLine|InvoiceDiscount|DiscountAllocation|Payment|PaymentApplication|CreditApplication|FinanceChargeInstallment)::(?:(?:query\(\)|where|find|findOrFail|first|firstOrFail|lockForUpdate|orderBy|whereIn|whereKey)\s*\([^;]*?\)\s*->\s*)*(?:create|update|upsert|insert|delete|firstOrCreate|updateOrCreate|firstOrNew)\s*\(/s',
        'direct money-model instance writer' => '/\$(?:financeCharge|invoiceLine|invoiceDiscount|discountAllocation|paymentApplication|creditApplication|payment|installment)\w*\s*->\s*(?:save|update|delete|increment|decrement|forceDelete)\s*\(/i',
        'direct money-model relation writer' => '/->\s*(?:invoiceLines|invoiceDiscounts|discountAllocations|payments|paymentApplications|creditApplications|installments)\s*\(\s*\)\s*->\s*(?:create|update|delete|firstOrCreate|updateOrCreate)\s*\(/',
        'direct money-table query writer' => '/(?:DB::(?:table|connection\s*\([^)]*\)\s*->\s*table)|->table)\s*\(\s*[\'\"](?:finance_charges|invoice_lines|invoice_discounts|discount_allocations|payments|payment_applications|credit_applications|finance_charge_installments)[\'\"]\s*\)\s*->\s*(?:insert|insertGetId|update|upsert|delete|increment|decrement)\s*\(/',
        'direct money-table statement writer' => '/DB::(?:statement|unprepared)\s*\(\s*[\'\"][^\'\"]*(?:finance_charges|invoice_lines|invoice_discounts|discount_allocations|payments|payment_applications|credit_applications|finance_charge_installments)[^\'\"]*[\'\"]\s*\)/i',
    ];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = str_starts_with($path, base_path('app').'/')
            ? 'app/'.str_replace(base_path('app').'/', '', $path)
            : 'app/'.basename($path);
        $contents = file_get_contents($path) ?: '';
        $guardedRanges = settlementMutationGuardedCallbackRanges($contents);

        foreach ($patterns as $label => $pattern) {
            preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);
            $hasUnguardedMatch = collect($matches[0] ?? [])->contains(
                static fn (array $match): bool => ! settlementMutationOffsetIsGuarded((int) $match[1], $guardedRanges),
            );

            if ($hasUnguardedMatch && ! isset($allowlist[$relativePath])) {
                $violations[] = "{$label}: {$relativePath}";
            }
        }
    }

    sort($violations);

    return $violations;
}

it('requires every legacy settlement bypass to declare an owner and removal wave', function (): void {
    $expectedPaths = [
        'app/Console/Commands/BackfillEgcBlocks.php',
        'app/Console/Commands/MigrateScholarshipDiscountsToInvoiceDiscounts.php',
        'app/Console/Commands/MigrateVoucherDiscountsToInvoiceDiscounts.php',
        'app/Console/Commands/BackfillStudentFees.php',
        'app/Console/Commands/BackfillVoidedChargeInstallments.php',
        'app/Modules/Finance/Actions/BackfillLegacyDeferCreditEntitlementsAction.php',
        'app/Modules/Finance/Actions/BackfillLegacyEgcExemptCreditEntitlementsAction.php',
        'app/Modules/Finance/Actions/BackfillLegacyScholarshipEntitlementsAction.php',
        'app/Modules/Finance/Actions/CloseKnownLegacyDataExceptionsAction.php',
    ];

    $actualPaths = array_keys(SettlementBypassAllowlist::entries());
    sort($actualPaths);
    sort($expectedPaths);

    expect($actualPaths)->toBe($expectedPaths);

    foreach (SettlementBypassAllowlist::entries() as $path => $entry) {
        expect($path)->toStartWith('app/')
            ->and($entry['owner'])->not->toBe('')
            ->and($entry['remove_by_wave'])->toMatch('/^wave-[0-6]$/')
            ->and(file_exists(base_path($path)))->toBeTrue();
    }
});

it('blocks new local settlement formulas and direct money-table writers', function (): void {
    $violations = settlementBypassViolations(base_path('app'));

    expect($violations)->toBe([], "Settlement bypasses outside the reviewed allowlist:\n".implode("\n", $violations));
});

it('recognizes only writes inside the settlement mutation guard callback as guarded', function (): void {
    $contents = <<<'PHP'
<?php
$this->settlementMutationGuard->handle($billingAccountId, function (): void {
    CreditApplication::query()->create(['amount' => -1]);
});
CreditApplication::query()->create(['amount' => -2]);
PHP;

    $ranges = settlementMutationGuardedCallbackRanges($contents);
    $guardedOffset = strpos($contents, "CreditApplication::query()->create(['amount' => -1])");
    $unguardedOffset = strpos($contents, "CreditApplication::query()->create(['amount' => -2])");

    expect($ranges)->toHaveCount(1)
        ->and($guardedOffset)->not->toBeFalse()
        ->and($unguardedOffset)->not->toBeFalse()
        ->and(settlementMutationOffsetIsGuarded((int) $guardedOffset, $ranges))->toBeTrue()
        ->and(settlementMutationOffsetIsGuarded((int) $unguardedOffset, $ranges))->toBeFalse();
});

it('detects supported direct settlement writer forms', function (): void {
    $contents = <<<'PHP'
<?php
FinanceCharge::create([]);
InvoiceLine::query()->where('id', 1)->update([]);
Payment::firstOrCreate([]);
PaymentApplication::updateOrCreate([], []);
$charge = FinanceCharge::firstOrNew([]);
$charge->save();
$installment->delete();
DB::table('finance_charge_installments')->upsert([]);
PHP;

    $directory = sys_get_temp_dir().'/settlement-bypass-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/Writer.php', $contents);

    try {
        expect(settlementBypassViolations($directory))
            ->toContain('direct money-model writer: app/Writer.php')
            ->toContain('direct money-model instance writer: app/Writer.php')
            ->toContain('direct money-table query writer: app/Writer.php');
    } finally {
        unlink($directory.'/Writer.php');
        rmdir($directory);
    }
});
