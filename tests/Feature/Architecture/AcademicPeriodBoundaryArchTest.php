<?php

declare(strict_types=1);

it('keeps current academic period persistence reads inside Academic Catalog', function (): void {
    $workspace = dirname(__DIR__, 3);
    $roots = [
        $workspace.'/app/Modules/Academic',
        $workspace.'/app/Modules/Finance',
        $workspace.'/app/Modules/Identity',
        $workspace.'/app/Modules/AI',
        $workspace.'/app/Services/V1',
        $workspace.'/app/Http/Controllers/Api/V1',
        $workspace.'/app/Support',
    ];
    $ownerRoot = $workspace.'/app/Modules/Academic/Catalog/';
    $patterns = [
        '/Semester::(?:query\(\)\s*->\s*)?where\(\s*[\'\"]is_active[\'\"]\s*,\s*true\s*\)/',
        '/Semester::firstWhere\(\s*[\'\"]is_active[\'\"]\s*,\s*true\s*\)/',
        '/Semester::getActiveSemester\s*\(/',
    ];
    $violations = [];

    foreach ($roots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_starts_with($file->getPathname(), $ownerRoot)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $file->getPathname();
                    break;
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Current Academic Period resolution must use AcademicPeriodReader outside Academic Catalog: '.implode(', ', $violations),
    );
});

it('keeps student Finance presentation period reads on the Academic contract', function (): void {
    $files = [
        base_path('app/Modules/Finance/Queries/GetStudentFinancePresentationQuery.php'),
        base_path('app/Modules/Finance/Queries/GetStudentPortalFinanceSummaryQuery.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Semester\s*;|->semester\b|with\(\s*[\'"]semester[\'"]/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty(
        'Student Finance presentation must resolve academic periods through AcademicPeriodReader.',
    );
});
