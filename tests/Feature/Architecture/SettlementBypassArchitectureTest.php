<?php

declare(strict_types=1);

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
    $hasGuardHelper = preg_match('/function\s+guard\s*\(\s*\)\s*:\s*SettlementMutationGuard\b/', $contents) === 1;
    for ($index = 0; $index < $tokenCount; $index++) {
        if (($positionedTokens[$index]['id'] ?? null) !== T_STRING
            || ! in_array($positionedTokens[$index]['text'], ['settlementMutationGuard', 'SettlementMutationGuard', 'guard'], true)) {
            continue;
        }

        $previousIndex = $index - 1;
        while ($previousIndex >= 0 && in_array($positionedTokens[$previousIndex]['id'] ?? null, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $previousIndex--;
        }
        $nextIndex = $index + 1;
        while ($nextIndex < $tokenCount && in_array($positionedTokens[$nextIndex]['id'] ?? null, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $nextIndex++;
        }

        $referenceIsGuard = match ($positionedTokens[$index]['text']) {
            'settlementMutationGuard' => ($positionedTokens[$previousIndex]['text'] ?? null) === '->',
            'SettlementMutationGuard' => ($positionedTokens[$nextIndex]['text'] ?? null) === '::',
            'guard' => $hasGuardHelper
                && ($positionedTokens[$previousIndex]['text'] ?? null) === '->'
                && ($positionedTokens[$nextIndex]['text'] ?? null) === '(',
            default => false,
        };
        if (! $referenceIsGuard) {
            continue;
        }

        $handleIndex = null;
        for ($candidate = $index + 1; $candidate < min($index + 24, $tokenCount); $candidate++) {
            if ($positionedTokens[$candidate]['text'] === ';') {
                break;
            }

            if (($positionedTokens[$candidate]['id'] ?? null) === T_STRING
                && in_array($positionedTokens[$candidate]['text'], ['handle', 'handleIfChanged'], true)) {
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

/** @return list<string> */
function settlementProtectedModels(): array
{
    return [
        'FinanceCharge',
        'StudentInvoice',
        'InvoiceLine',
        'InvoiceDiscount',
        'DiscountAllocation',
        'Payment',
        'PaymentApplication',
        'CreditApplication',
        'FinanceChargeInstallment',
        'DngPaymentRequest',
        'DngPaymentRequestCharge',
        'DngPaymentRequestReservationTarget',
    ];
}

/** @return list<string> */
function settlementProtectedTables(): array
{
    return [
        'finance_charges',
        'student_invoices',
        'invoice_lines',
        'invoice_discounts',
        'discount_allocations',
        'payments',
        'payment_applications',
        'credit_applications',
        'finance_charge_installments',
        'dng_payment_requests',
        'dng_payment_request_charges',
        'dng_payment_request_reservation_targets',
    ];
}

/** @return list<string> */
function settlementProtectedMutationColumns(): array
{
    return [
        'status',
        'amount',
        'amount_snapshot',
        'subtotal',
        'discount_total',
        'total_amount',
        'paid_amount',
        'cached_subtotal',
        'cached_discount_total',
        'cached_total_amount',
        'cached_paid_amount',
        'cached_paid_at',
        'voided_at',
        'void_reason',
        'voided_by_user_id',
        'payment_id',
        'billing_account_id',
        'dng_payment_request_id',
        'active_slot_key',
        'captured_settlement_version',
        'target_fingerprint',
        'captured_collectible',
        'finance_charge_installment_id',
        'paid_at',
        'reversed_at',
        'error_message',
        'review_evidence',
        'dng_payment_id',
        'last_callback_payload',
        'invoice_serial_number',
        'invoice_date',
    ];
}

/**
 * @return list<array{0:string,1:int}>
 */
function settlementProtectedInstanceWriterMatches(string $contents): array
{
    $models = implode('|', array_map(preg_quote(...), settlementProtectedModels()));
    $tables = implode('|', array_map(preg_quote(...), settlementProtectedTables()));
    $settlementRepository = '(?:Settlement|Ledger|Finance|Charge|Invoice|Payment|Credit|Discount|Installment|Dng)[A-Za-z_0-9]*(?:Repository|Writer)';
    $variables = [];
    $sourcePatterns = [
        '/\$(?<variable>[A-Za-z_]\w*)\s*=\s*(?:'.$models.')::/',
        '/(?:'.$models.')(?:\|null)?\s+\$(?<variable>[A-Za-z_]\w*)/',
        '/\$(?<variable>[A-Za-z_]\w*)\s*=\s*\$[A-Za-z_]\w*->(?:dngPaymentRequest|invoice|charge|payment)\b/',
        '/\$(?<variable>[A-Za-z_]\w*)\s*=\s*DB::table\(\s*[\'\"](?:'.$tables.')[\'\"]\s*\)/',
        '/\$(?<variable>[A-Za-z_]\w*)\s*=\s*app\(\s*'.$settlementRepository.'::class\s*\)/',
        '/'.$settlementRepository.'\s+\$(?<variable>[A-Za-z_]\w*)/',
    ];

    foreach ($sourcePatterns as $sourcePattern) {
        preg_match_all($sourcePattern, $contents, $matches);
        foreach ($matches['variable'] ?? [] as $variable) {
            $variables[$variable] = true;
        }
    }

    do {
        $knownCount = count($variables);
        foreach (array_keys($variables) as $sourceVariable) {
            preg_match_all(
                '/\$(?<variable>[A-Za-z_]\w*)\s*=\s*\$'.preg_quote($sourceVariable, '/').'\s*;/',
                $contents,
                $matches,
            );
            foreach ($matches['variable'] ?? [] as $variable) {
                $variables[$variable] = true;
            }
        }

        foreach (array_keys($variables) as $collectionVariable) {
            preg_match_all(
                '/foreach\s*\(\s*\$'.preg_quote($collectionVariable, '/').'\s+as\s+\$(?<variable>[A-Za-z_]\w*)\s*\)/',
                $contents,
                $matches,
            );
            foreach ($matches['variable'] ?? [] as $variable) {
                $variables[$variable] = true;
            }
        }
    } while (count($variables) > $knownCount);

    if ($variables === []) {
        return [];
    }

    $variablePattern = implode('|', array_map(
        static fn (string $variable): string => preg_quote($variable, '/'),
        array_keys($variables),
    ));
    $writerPattern = '/\$(?:'.$variablePattern.')\s*->\s*(?:(?:forceFill|fill)\s*\([^;]*?\)\s*->\s*)?(?:create|insert|upsert|firstOrCreate|updateOrCreate|save|update|delete|increment|decrement|forceDelete|touch|transitionTo|store|persist|remove)\s*\([^;]*?\)/s';
    preg_match_all($writerPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

    return collect($matches[0] ?? [])->filter(function (array $match): bool {
        if (! preg_match('/->\s*update\s*\(\s*\[(?<fields>.*)\]\s*\)$/s', $match[0], $updateMatch)) {
            return true;
        }

        return collect(settlementProtectedMutationColumns())->contains(
            static fn (string $column): bool => preg_match('/[\'\"]'.preg_quote($column, '/').'[\'\"]\s*=>/', $updateMatch['fields']) === 1,
        );
    })->values()->all();
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
    $protectedModels = implode('|', array_map(preg_quote(...), settlementProtectedModels()));
    $protectedTables = implode('|', array_map(preg_quote(...), settlementProtectedTables()));
    $patterns = [
        'local settlement formula' => '/(?:amount_snapshot|fc\.amount|finance_charges\.amount|total_amount)\s*-\s*(?:.*(?:paid|payment|discount|credit))/i',
        'direct money-model writer' => '/\b(?:'.$protectedModels.')::(?:(?:query\(\)|where|find|findOrFail|first|firstOrFail|lockForUpdate|orderBy|whereIn|whereKey)\s*\([^;]*?\)\s*->\s*)*(?:create|update|upsert|insert|delete|firstOrCreate|updateOrCreate|firstOrNew)\s*\(/s',
        'direct money-model relation writer' => '/->\s*(?:invoiceLines|invoiceDiscounts|discountAllocations|payments|paymentApplications|creditApplications|installments|reservationTargets|charges)\s*\(\s*\)\s*->\s*(?:create|update|delete|firstOrCreate|updateOrCreate|upsert)\s*\(/',
        'direct protected repository writer' => '/(?:\b(?:'.$protectedModels.')(?:Repository|Writer)::\s*(?:create|store|persist|save|update|upsert|delete|remove)\s*\(|\$(?<repository>[A-Za-z_]\w*)\s*=\s*app\(\s*(?:'.$protectedModels.')(?:Repository|Writer)::class\s*\)\s*;\s*\$\k<repository>\s*->\s*(?:create|store|persist|save|update|upsert|delete|remove)\s*\()/s',
        'direct money-table query writer' => '/(?:DB::(?:table|connection\s*\([^)]*\)\s*->\s*table)|->table)\s*\(\s*[\'\"](?:'.$protectedTables.')[\'\"]\s*\)(?:(?!;).)*?->\s*(?:insert|insertGetId|update|upsert|delete|increment|decrement)\s*\(/s',
        'direct money-table statement writer' => '/DB::(?:statement|unprepared)\s*\(\s*[\'\"][^\'\"]*(?:'.$protectedTables.')[^\'\"]*[\'\"]\s*\)/i',
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

        $instanceMatches = settlementProtectedInstanceWriterMatches($contents);
        if (collect($instanceMatches)->contains(
            static fn (array $match): bool => ! settlementMutationOffsetIsGuarded((int) $match[1], $guardedRanges),
        )) {
            $violations[] = "direct money-model instance writer: {$relativePath}";
        }

        foreach ($patterns as $label => $pattern) {
            preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);
            $hasUnguardedMatch = collect($matches[0] ?? [])->contains(
                static fn (array $match): bool => ! settlementMutationOffsetIsGuarded((int) $match[1], $guardedRanges),
            );

            $hasLocalFormula = $label === 'local settlement formula' && ($matches[0] ?? []) !== [];

            if ($hasLocalFormula || $hasUnguardedMatch) {
                $violations[] = "{$label}: {$relativePath}";
            }
        }
    }

    sort($violations);

    return $violations;
}

it('blocks every local settlement formula and direct protected-table writer', function (): void {
    $violations = settlementBypassViolations(base_path('app'));

    expect($violations)->toBe([], "Settlement bypasses must run through SettlementMutationGuard:\n".implode("\n", $violations));
});

it('recognizes only writes inside the settlement mutation guard callback as guarded', function (): void {
    $contents = <<<'PHP'
<?php
$this->settlementMutationGuard->handle($billingAccountId, function (): void {
    CreditApplication::query()->create(['amount' => -1]);
});
CreditApplication::query()->create(['amount' => -2]);
$this->guard->handle($billingAccountId, function (): void {
    CreditApplication::query()->create(['amount' => -3]);
});
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
$unexpectedName = FinanceCharge::firstOrNew([]);
$unexpectedName->save();
$anotherUnexpectedName = DngPaymentRequest::firstOrNew([]);
$anotherUnexpectedName->transitionTo('cancelled');
$repository = app(FinanceChargeRepository::class);
$repository->save($unexpectedName);
$builder = FinanceCharge::query();
$aliasedBuilder = $builder;
$aliasedBuilder->upsert([]);
$tableBuilder = DB::table('finance_charges');
$tableBuilder->update(['status' => 'void']);
$ledgerRepository = app(SettlementLedgerRepository::class);
$ledgerRepository->persist($unexpectedName);
StudentInvoice::query()->update(['paid_amount' => 10]);
DB::table('finance_charge_installments')->upsert([]);
DB::table('dng_payment_request_reservation_targets')->where('id', 1)->delete();
PHP;

    $directory = sys_get_temp_dir().'/settlement-bypass-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/Writer.php', $contents);

    try {
        expect(settlementBypassViolations($directory))
            ->toContain('direct money-model writer: app/Writer.php')
            ->toContain('direct money-model instance writer: app/Writer.php')
            ->toContain('direct protected repository writer: app/Writer.php')
            ->toContain('direct money-table query writer: app/Writer.php');
    } finally {
        unlink($directory.'/Writer.php');
        rmdir($directory);
    }
});

it('recognizes handleIfChanged callbacks as guarded', function (): void {
    $contents = <<<'PHP'
<?php
$this->settlementMutationGuard->handleIfChanged($billingAccountId, function (): void {
    $unexpectedName = DngPaymentRequest::query()->findOrFail(1);
    $unexpectedName->transitionTo('cancelled');
});
PHP;

    $ranges = settlementMutationGuardedCallbackRanges($contents);
    $writerOffset = strpos($contents, '$unexpectedName->transitionTo');

    expect($ranges)->toHaveCount(1)
        ->and($writerOffset)->not->toBeFalse()
        ->and(settlementMutationOffsetIsGuarded((int) $writerOffset, $ranges))->toBeTrue();
});
