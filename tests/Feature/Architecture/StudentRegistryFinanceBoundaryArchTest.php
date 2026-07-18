<?php

declare(strict_types=1);

it('keeps the Finance student search and overview tracer on the Registry contract', function (): void {
    $files = [
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php'),
        base_path('app/Modules/Finance/Queries/Audit/ResolveFinanceAuditSearchQuery.php'),
        base_path('app/Modules/Finance/Queries/Audit/GetFinanceAuditGraphQuery.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Student\s*;|\bStudent::|->student\b|whereHas\(\'student\'/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance identity reads must use StudentReferenceReader outside the Registry adapter.');
});
