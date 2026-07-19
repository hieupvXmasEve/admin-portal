<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps AI and reporting reads behind owner contracts', function (): void {
    $sourceRoot = base_path('app/Modules/AI');
    $forbiddenImports = [
        'source model' => '/^use App\\\\Models\\\\(?!(?:Campus|User)(?:;|\\s+as\\s+\\w+;))\\w+(?:;|\\s+as\\s+\\w+;)/m',
        'concrete owner implementation' => '/^use App\\\\Modules\\\\(?:Academic|Facilities|Finance|Identity|Institution|Notification|StudentRegistry)\\\\(?:Actions|Models|Queries|Services)\\\\/m',
    ];

    $violations = collect(File::allFiles($sourceRoot))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $file) use ($forbiddenImports): array {
            $contents = $file->getContents();

            return collect($forbiddenImports)
                ->filter(fn (string $pattern): bool => preg_match($pattern, $contents) === 1)
                ->keys()
                ->map(fn (string $importType): string => $file->getRelativePathname()." imports a {$importType}")
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBe([], 'AI and reporting must obtain source facts through owner readers: '.implode(', ', $violations));
});
