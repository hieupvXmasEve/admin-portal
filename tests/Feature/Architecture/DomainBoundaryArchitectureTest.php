<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps top-level contexts independent of concrete module implementations', function (): void {
    $modulesRoot = base_path('app/Modules');
    $violations = collect(File::directories($modulesRoot))
        ->flatMap(function (string $moduleRoot): array {
            $consumer = basename($moduleRoot);

            return collect(File::allFiles($moduleRoot))
                ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
                ->flatMap(function (SplFileInfo $file) use ($consumer): array {
                    preg_match_all(
                        '/^use App\\\\Modules\\\\(?<owner>[A-Za-z]+)\\\\(?<implementation>[^;]+);/m',
                        $file->getContents(),
                        $matches,
                        PREG_SET_ORDER,
                    );

                    return collect($matches)
                        ->reject(fn (array $match): bool => $match['owner'] === $consumer)
                        ->map(fn (array $match): string => sprintf(
                            '%s imports %s\\%s',
                            $consumer.'/'.$file->getRelativePathname(),
                            $match['owner'],
                            $match['implementation'],
                        ))
                        ->all();
                })
                ->all();
        })
        ->sort()
        ->values()
        ->all();

    expect($violations)->toBe(
        [],
        'Contexts must collaborate through Shared Contracts, Domain Events, or projections: '.implode(', ', $violations),
    );
});
