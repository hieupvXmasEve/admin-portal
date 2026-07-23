<?php

declare(strict_types=1);

it('owns system configuration routes in Platform and retires the legacy implementation', function (): void {
    $root = dirname(__DIR__, 3);
    $retiredPaths = [
        $root.'/app/Services/SystemConfigService.php',
        $root.'/app/Http/Controllers/Api/SystemConfigController.php',
        $root.'/app/Http/Controllers/Web/SystemConfigController.php',
        $root.'/app/Http/Requests/SystemConfigUpdateRequest.php',
    ];

    foreach ($retiredPaths as $path) {
        expect($path)->not->toBeFile();
    }

    expect(route('api.system-config.index'))->toContain('/api/system-config')
        ->and(app('router')->getRoutes()->getByName('api.system-config.update')?->getActionName())
        ->toBe('App\\Modules\\Platform\\Http\\Api\\SystemConfigurationController@update')
        ->and(app('router')->getRoutes()->getByName('system.config.index')?->getActionName())
        ->toBe('App\\Modules\\Platform\\Http\\Web\\Admin\\SystemConfigurationController@index');
});

it('does not retain supported runtime callers of the retired system configuration service', function (): void {
    $runtimeRoots = [
        dirname(__DIR__, 3).'/app',
        dirname(__DIR__, 3).'/routes',
        dirname(__DIR__, 3).'/config',
        dirname(__DIR__, 3).'/database',
    ];
    $violations = [];

    foreach ($runtimeRoots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';
            if (str_contains($contents, 'SystemConfigService') || str_contains($contents, 'SystemConfigController')) {
                $violations[] = $file->getPathname();
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Supported runtime callers must use the Platform SystemConfigurationReader boundary.',
    );
});
