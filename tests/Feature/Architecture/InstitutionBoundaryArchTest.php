<?php

declare(strict_types=1);

it('keeps Institution campus and department writes inside the Institution owner', function (): void {
    $forbiddenWrites = [
        'Campus' => '/Campus::(?:query\(\)\s*->\s*)?(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy)\s*\(/',
        'Department' => '/Department::(?:query\(\)\s*->\s*)?(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy)\s*\(/',
        'DepartmentMembership' => '/DepartmentMembership::(?:query\(\)\s*->\s*)?(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy)\s*\(/',
    ];
    $instanceVariables = [
        'Campus' => 'campus',
        'Department' => 'department',
        'DepartmentMembership' => 'membership',
    ];
    $roots = [
        dirname(__DIR__, 3).'/app/Modules/Academic',
        dirname(__DIR__, 3).'/app/Modules/Finance',
        dirname(__DIR__, 3).'/app/Modules/Facilities',
    ];
    $violations = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getPathname() === dirname(__DIR__, 3).'/app/Modules/Finance/Support/LegacyDngCampusMappingWriter.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            foreach ($forbiddenWrites as $model => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $file->getPathname().' writes '.$model.' statically';
                }

                if (preg_match('/\$'.$instanceVariables[$model].'\s*->\s*(?:save|update|delete)\s*\(/', $contents) === 1) {
                    $violations[] = $file->getPathname().' mutates a '.$model.' instance';
                }
            }
        }
    }

    expect($violations)->toBeEmpty('Campus and Department writes must go through Institution owner actions or contracts.');
});
