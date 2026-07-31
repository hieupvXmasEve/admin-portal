<?php

declare(strict_types=1);

/**
 * Red-proof for the Academic↔Finance\Models arch rule.
 *
 * Pest arch rules evaluate the live codebase, not temporary fixtures, so this
 * test encodes the invariant as an explicit source scan that fails if a
 * deliberate forbidden import is present. The companion arch() suite is the
 * CI enforcement seam; this file documents and double-checks the same rule.
 */
it('flags a deliberate Academic import of a Finance money model as a boundary violation', function () {
    $fixture = <<<'PHP'
    <?php
    namespace App\Modules\Academic\Support;
    use App\Modules\Finance\Models\FinanceCharge;
    class ForbiddenFinanceModelImportFixture {}
    PHP;

    $violations = [];
    if (preg_match('/use\s+App\\\\Modules\\\\Finance\\\\Models\\\\\w+/', $fixture) === 1) {
        $violations[] = 'App\Modules\Academic\Support\ForbiddenFinanceModelImportFixture';
    }

    expect($violations)->not->toBeEmpty('Red-proof must detect the forbidden import pattern.');

    // Live tree must stay clean (same rule as arch()). Broadened (P3,
    // scholarship adjustment) beyond \Models\ to ANY App\Modules\Finance\
    // import — Academic's only door into Finance is App\Shared\Contracts\.
    $liveViolations = [];
    $academicRoot = dirname(__DIR__, 3).'/app/Modules/Academic';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($academicRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Modules\\\\Finance\\\\/', $contents) === 1) {
            $liveViolations[] = $file->getPathname();
        }
    }

    expect($liveViolations)->toBeEmpty('Live Academic code must not import any App\\Modules\\Finance\\ namespace.');
});

it('flags a deliberate Finance import of an Academic source model as a boundary violation', function () {
    $fixture = <<<'PHP'
    <?php
    namespace App\Modules\Finance\Support;
    use App\Models\ExamResitAttempt;
    class ForbiddenAcademicModelImportFixture {
        public function handle(): void { ExamResitAttempt::query(); }
    }
    PHP;

    $violations = [];
    if (preg_match('/use\s+App\\\\Models\\\\(CourseRegistration|CourseRetakeRegistration|ExamResitAttempt|EgcBlock|AcademicRecord)\s*;/', $fixture) === 1
        || preg_match('/(?<![A-Za-z0-9_\\\\])(CourseRegistration|CourseRetakeRegistration|ExamResitAttempt|EgcBlock|AcademicRecord)::/', $fixture) === 1) {
        $violations[] = 'App\Modules\Finance\Support\ForbiddenAcademicModelImportFixture';
    }

    expect($violations)->not->toBeEmpty('Red-proof must detect the forbidden Finance -> Academic model pattern.');

    // Live tree: Finance must never import AcademicRecord — Phase 5's
    // restoration flow must read Academic state only through a Shared contract.
    $liveViolations = [];
    $financeRoot = dirname(__DIR__, 3).'/app/Modules/Finance';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($financeRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\AcademicRecord\s*;/', $contents) === 1) {
            $liveViolations[] = $file->getPathname();
        }
    }

    expect($liveViolations)->toBeEmpty('Live Finance code must not import App\\Models\\AcademicRecord.');
});
