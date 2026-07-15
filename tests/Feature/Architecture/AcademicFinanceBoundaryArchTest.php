<?php

declare(strict_types=1);

/**
 * ADR-0026 / issue 05: Academic and Finance money models must not cross-import.
 *
 * Money models live under App\Modules\Finance\Models. Cross-context access goes
 * only through app/Shared/Contracts/Finance.
 *
 * CI note (D1): this suite only bites once CI is re-enabled. Local/agent runs
 * should include tests/Feature/Architecture when touching the boundary.
 */
arch('Academic module never imports Finance money models')
    ->expect('App\Modules\Academic')
    ->not->toUse('App\Modules\Finance\Models');

arch('Finance money models never import Academic module')
    ->expect('App\Modules\Finance\Models')
    ->not->toUse('App\Modules\Academic');

it('keeps Finance source workflows off Academic-owned Eloquent models', function () {
    $forbiddenModels = [
        'CourseRegistration',
        'CourseRetakeRegistration',
        'ExamResitAttempt',
        'EgcBlock',
    ];

    $violations = [];
    $financeRoot = dirname(__DIR__, 3).'/app/Modules/Finance';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($financeRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        foreach ($forbiddenModels as $model) {
            if (preg_match('/use\s+App\\\\Models\\\\'.$model.'\s*;/', $contents) === 1
                || preg_match('/(?<![A-Za-z0-9_\\\\])'.$model.'::/', $contents) === 1) {
                $violations[] = $file->getPathname().' uses '.$model;
            }
        }
    }

    expect($violations)->toBeEmpty('Finance source workflows must use Academic contracts, not Academic Eloquent models.');
});
