<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('places every CRM NE sync class, command, and controller under the Admissions module', function (): void {
    $mustExist = [
        'app/Modules/Admissions/Integrations/Crm/CrmClient.php',
        'app/Modules/Admissions/Support/Crm/CrmApplicationMapper.php',
        'app/Modules/Admissions/Support/Crm/CrmDocumentUrlValidator.php',
        'app/Modules/Admissions/Support/Crm/CrmMappingSettings.php',
        'app/Modules/Admissions/Services/CrmApplicationSyncService.php',
        'app/Modules/Admissions/Services/CrmMappingResolver.php',
        'app/Modules/Admissions/Console/SyncCrmApplicationsCommand.php',
        'app/Modules/Admissions/Http/Web/CrmMappingController.php',
        'app/Modules/Admissions/Queries/ListUnmappedCrmValuesQuery.php',
        'app/Modules/Admissions/Queries/GetApplicationConversionReadinessQuery.php',
        'app/Modules/Admissions/Models/CrmValueMapping.php',
        'app/Modules/Admissions/Models/ApplicationAcademicScore.php',
    ];

    foreach ($mustExist as $path) {
        expect(base_path($path))->toBeFile();
    }
});

it('has no stray CRM sync copy under the global app/Http or app/Console trees', function (): void {
    $globalHttpFiles = File::exists(app_path('Http/Controllers'))
        ? collect(File::allFiles(app_path('Http/Controllers')))->map(fn ($file) => $file->getPathname())
        : collect();
    $globalConsoleFiles = File::exists(app_path('Console'))
        ? collect(File::allFiles(app_path('Console')))->map(fn ($file) => $file->getPathname())
        : collect();

    $suspect = $globalHttpFiles->merge($globalConsoleFiles)->filter(
        fn (string $path): bool => str_contains($path, 'Crm') || str_contains($path, 'CrmMapping'),
    );

    expect($suspect->all())->toBeEmpty();
});
