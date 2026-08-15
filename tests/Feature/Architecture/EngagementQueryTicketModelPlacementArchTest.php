<?php

declare(strict_types=1);

/**
 * Placement guard for the Engagement-owned Query/Ticketing sub-batch
 * (sub-PR 4c). The real classes must live under
 * app/Modules/Engagement/Models. All 4 app/Models shims for this batch
 * (including QueryReply/QueryTicket, swept in plan 260815-1320 phase 2)
 * have been deleted.
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

it('has deleted the app/Models shim for QueryAssignment, QueryReply, QueryTicket, QueryTopic', function (): void {
    $workspace = dirname(__DIR__, 3);

    // QueryReply and QueryTicket joined the swept group once the Upload ->
    // Engagement inverse relations (UploadRecord::queryReply/response/ticket)
    // were confirmed unused and deleted. The name-collision negative-reference
    // test formerly here (checking for stale legacy-namespace references to
    // FormResponse, FormTarget, QueryReply, and QueryTicket inside
    // app/Modules/Engagement) is now redundant: DeprecatedModelShimArchTest
    // guards all 30 migrated names repo-wide, and all 4 of those names are
    // swept.
    $sweptModels = ['QueryAssignment', 'QueryReply', 'QueryTicket', 'QueryTopic'];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});
