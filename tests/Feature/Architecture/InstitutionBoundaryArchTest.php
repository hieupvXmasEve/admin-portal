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

it('does not retain the retired legacy campus reference-management actions', function (): void {
    $legacyActionDirectory = dirname(__DIR__, 3).'/app/Actions/Campus';

    expect(glob($legacyActionDirectory.'/*.php'))->toBeEmpty(
        'Campus administration is owned by Institution; do not reintroduce the retired legacy action path.',
    );
});

it('does not retain runtime consumers of the retired legacy campus actions', function (): void {
    $runtimeRoots = [
        dirname(__DIR__, 3).'/app',
        dirname(__DIR__, 3).'/routes',
        dirname(__DIR__, 3).'/config',
        dirname(__DIR__, 3).'/database',
    ];
    $legacyNamespace = 'App\\Actions\\Campus\\';
    $violations = [];

    foreach ($runtimeRoots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            if (str_contains($contents, $legacyNamespace)) {
                $violations[] = $file->getPathname();
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Supported runtime callers must migrate to Institution ownership before legacy campus actions are retired.',
    );
});
