<?php

declare(strict_types=1);

it('keeps Identity lecturer authorization off Faculty Workforce persistence', function (): void {
    $identityRoot = base_path('app/Modules/Identity');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($identityRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Lecture\s*;|\bLecture::|->lecturer\b|\b(employment_status|contract_end_date|is_available_for_assignment)\b/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Identity lecturer authorization must read only Account Status and Lecturer Access Grant; Workforce state arrives through its contract.',
    );
});
