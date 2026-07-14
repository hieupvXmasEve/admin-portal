<?php

declare(strict_types=1);

/**
 * ADR-0028 / wave 7: FinanceCharge is a debit read model written only by the
 * Finance materializer (CreateFinanceChargeAction via RequestFinanceDebitAction).
 *
 * Production app code must not call FinanceCharge::create outside the materializer.
 * Callers of CreateFinanceChargeAction outside RequestFinanceDebitAction are also
 * forbidden (legacy one-shot repair commands remain allowlisted until deleted).
 */

/**
 * @return list<string>
 */
function scanPhpFilesForPattern(string $root, string $pattern, array $allowlistBasenames = []): array
{
    $violations = [];

    if (! is_dir($root)) {
        return $violations;
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $basename = $file->getBasename();
        if (in_array($basename, $allowlistBasenames, true)) {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match($pattern, $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    sort($violations);

    return $violations;
}

it('allows FinanceCharge::create only inside CreateFinanceChargeAction (materializer)', function () {
    $appRoot = dirname(__DIR__, 3).'/app';
    $pattern = '/FinanceCharge::(?:query\(\)\s*->\s*)?create\s*\(/';

    $violations = scanPhpFilesForPattern($appRoot, $pattern, [
        'CreateFinanceChargeAction.php',
    ]);

    expect($violations)->toBe(
        [],
        "FinanceCharge::create outside materializer:\n".implode("\n", $violations)
    );
});

it('allows CreateFinanceChargeAction usage only from the debit materializer path', function () {
    $appRoot = dirname(__DIR__, 3).'/app';
    // Import, type-hint, ::class, or construction — not prose comments.
    $pattern = '/(?:use\s+App\\\\Modules\\\\Finance\\\\Actions\\\\CreateFinanceChargeAction\b|CreateFinanceChargeAction\s*\$|CreateFinanceChargeAction::class|new\s+CreateFinanceChargeAction\b)/';

    $violations = scanPhpFilesForPattern($appRoot, $pattern, [
        // The materializer writer itself.
        'CreateFinanceChargeAction.php',
        // Sole production materializer entry (intake debit path).
        'RequestFinanceDebitAction.php',
    ]);

    expect($violations)->toBe(
        [],
        "CreateFinanceChargeAction referenced outside materializer path:\n".implode("\n", $violations)
    );
});

it('keeps Academic free of Finance money model imports (boundary arch)', function () {
    $academicRoot = dirname(__DIR__, 3).'/app/Modules/Academic';
    $violations = scanPhpFilesForPattern(
        $academicRoot,
        '/use\s+App\\\\Modules\\\\Finance\\\\Models\\\\/',
    );

    expect($violations)->toBeEmpty('Academic must not import Finance\\Models.');
});

it('does not auto-create FinanceCharge rows from DNG push actions', function () {
    $dngRoots = [
        dirname(__DIR__, 3).'/app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php',
        dirname(__DIR__, 3).'/app/Modules/Finance/Dng',
    ];

    $violations = [];
    $pattern = '/FinanceCharge::(?:query\(\)\s*->\s*)?create\s*\(/';

    foreach ($dngRoots as $root) {
        if (is_file($root)) {
            $contents = file_get_contents($root) ?: '';
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = $root;
            }

            continue;
        }

        $violations = array_merge($violations, scanPhpFilesForPattern($root, $pattern));
    }

    expect($violations)->toBe(
        [],
        "DNG paths must not create FinanceCharge rows:\n".implode("\n", $violations)
    );
});
