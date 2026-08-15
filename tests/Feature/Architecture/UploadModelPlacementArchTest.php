<?php

declare(strict_types=1);

/**
 * Placement guard for the Upload-owned document models. The real classes
 * must live under app/Modules/Upload/Models; the app/Models copy is a
 * class_alias shim kept only for cross-module backward compatibility.
 */
it('keeps UploadRecord, ApplicationDocument, ApplicationDocumentType inside Upload module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['UploadRecord', 'ApplicationDocument', 'ApplicationDocumentType'];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Upload/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Upload/Models.");

        $fqcn = "App\\Modules\\Upload\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has no real (non-shim) UploadRecord, ApplicationDocument class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // ApplicationDocumentType left this list once Admissions stopped reading
    // the document-type catalog directly and went through
    // App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader.
    // ApplicationDocument stays blocked: it is written, not just read — see
    // DeprecatedModelShimArchTest for why that write path is not swept yet.
    $models = ['UploadRecord', 'ApplicationDocument'];

    foreach ($models as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});

it('has deleted the app/Models shim for ApplicationDocumentType', function (): void {
    $workspace = dirname(__DIR__, 3);
    $model = 'ApplicationDocumentType';

    $legacyPath = $workspace."/app/Models/{$model}.php";
    expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
    expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
});
