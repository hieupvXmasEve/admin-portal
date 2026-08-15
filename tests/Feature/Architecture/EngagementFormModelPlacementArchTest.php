<?php

declare(strict_types=1);

/**
 * Placement guard for the Engagement-owned Forms sub-batch (sub-PR 4b). The
 * real classes must live under app/Modules/Engagement/Models. All 7 app/Models
 * shims for this batch, including FormResponse (its Upload<->Engagement
 * inverse relations had zero real consumers), have been deleted.
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

it('has deleted the app/Models shim for the 7 swept Forms models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // FormTarget joined the swept group once Academic stopped reading a
    // course's survey target directly and went through
    // App\Shared\Contracts\Engagement\CourseSurveyTargetReader instead.
    // FormResponse joined once the Upload<->Engagement inverse relations
    // (UploadRecord::queryReply/response/ticket) were confirmed unused and
    // deleted, and the 4 in-namespace bare `FormResponse::class` bindings in
    // app/Models were qualified with explicit imports.
    $sweptModels = [
        'Form',
        'FormResponse',
        'FormResultVisibility',
        'FormSection',
        'FormSurvey',
        'FormTarget',
        'FormVersion',
    ];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});
