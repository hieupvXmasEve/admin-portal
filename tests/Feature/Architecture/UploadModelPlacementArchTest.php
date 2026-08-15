<?php

declare(strict_types=1);

/**
 * Placement guard for the Upload-owned document models. The real classes
 * must live under app/Modules/Upload/Models. All 3 app/Models shims for
 * this batch, including ApplicationDocument (its cross-module write from
 * CRM ingest now goes through App\Shared\Contracts\Upload\ApplicationDocumentWriter
 * instead — see ADR-0050), have been deleted.
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

it('has deleted the app/Models shim for ApplicationDocumentType, UploadRecord, and ApplicationDocument', function (): void {
    $workspace = dirname(__DIR__, 3);

    // ApplicationDocumentType left this list once Admissions stopped reading
    // the document-type catalog directly and went through
    // App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader.
    // UploadRecord joined once Engagement's query-reply and form-response
    // attachment reads went through App\Shared\Contracts\Upload\UploadRecordReader
    // instead. ApplicationDocument joined last, once Admissions' CRM-ingest
    // write path went through App\Shared\Contracts\Upload\ApplicationDocumentWriter
    // instead of the Eloquent model directly (ADR-0050).
    $sweptModels = ['ApplicationDocumentType', 'UploadRecord', 'ApplicationDocument'];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});
