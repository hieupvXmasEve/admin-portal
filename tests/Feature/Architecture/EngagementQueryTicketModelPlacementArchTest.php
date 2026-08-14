<?php

declare(strict_types=1);

/**
 * Placement guard for the Engagement-owned Query/Ticketing sub-batch
 * (sub-PR 4c). The real classes must live under
 * app/Modules/Engagement/Models; the app/Models copy is a class_alias shim
 * kept only for cross-module backward compatibility.
 */
it('keeps QueryAssignment, QueryReply, QueryTicket, QueryTopic inside Engagement module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['QueryAssignment', 'QueryReply', 'QueryTicket', 'QueryTopic'];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Engagement/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Engagement/Models.");

        $fqcn = "App\\Modules\\Engagement\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has deleted the app/Models shim for QueryAssignment and QueryTopic', function (): void {
    $workspace = dirname(__DIR__, 3);

    $sweptModels = ['QueryAssignment', 'QueryTopic'];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});

it('has no real (non-shim) QueryReply/QueryTicket model class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // QueryReply and QueryTicket stay blocked: an Upload <-> Engagement
    // cross-module read still resolves them through this shim, and
    // rewriting that caller to the canonical namespace would trip the
    // zero-tolerance cross_context_concrete_imports boundary rule.
    $blockedModels = ['QueryReply', 'QueryTicket'];

    foreach ($blockedModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});

it('has no legacy App\\Models\\{FormResponse,FormTarget,QueryReply,QueryTicket} references remaining inside app/Modules/Engagement', function (): void {
    $root = dirname(__DIR__, 3).'/app/Modules/Engagement';
    // Only the 4 models still shimmed at app/Models stay in this negative
    // list. The other 12 Engagement models that used to appear here now
    // have their own app/Models shim deleted, so DeprecatedModelShimArchTest
    // (repo-wide, all 30 migrated names) is what guards against them
    // reappearing — this list only needs to cover names that could still be
    // legitimately confused with a real cross-module shim import.
    $legacyClasses = [
        'App\\Models\\FormResponse', 'App\\Models\\FormTarget',
        'App\\Models\\QueryReply', 'App\\Models\\QueryTicket',
    ];
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        foreach ($legacyClasses as $legacyClass) {
            if (preg_match('/'.preg_quote($legacyClass, '/').'\b/', $contents)) {
                $violations[] = $file->getPathname().' references '.$legacyClass;
            }
        }
    }

    expect($violations)->toBeEmpty(implode(', ', $violations));
});
