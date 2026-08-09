<?php

declare(strict_types=1);

/**
 * Placement guard for the Engagement-owned Forms sub-batch (sub-PR 4b). The
 * real classes must live under app/Modules/Engagement/Models; the
 * app/Models copy is a class_alias shim kept only for cross-module backward
 * compatibility.
 */
it('keeps the 7 Forms models inside the Engagement module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = [
        'Form',
        'FormResponse',
        'FormResultVisibility',
        'FormSection',
        'FormSurvey',
        'FormTarget',
        'FormVersion',
    ];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Engagement/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Engagement/Models.");

        $fqcn = "App\\Modules\\Engagement\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has no real (non-shim) Forms sub-batch model class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = [
        'Form',
        'FormResponse',
        'FormResultVisibility',
        'FormSection',
        'FormSurvey',
        'FormTarget',
        'FormVersion',
    ];

    foreach ($models as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});
