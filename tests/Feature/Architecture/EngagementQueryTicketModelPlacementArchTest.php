<?php

declare(strict_types=1);

/**
 * Placement guard for the Engagement-owned Query/Ticketing sub-batch
 * (sub-PR 4c). The real classes must live under
 * app/Modules/Engagement/Models; the app/Models copy is a class_alias shim
 * kept only for cross-module backward compatibility.
 */
it('keeps QueryAssignment, QueryReply, QueryTicket, QueryTopic inside Engagement module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['QueryAssignment', 'QueryReply', 'QueryTicket', 'QueryTopic'];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Engagement/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Engagement/Models.");

        $fqcn = "App\\Modules\\Engagement\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has no real (non-shim) Query/Ticketing sub-batch model class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['QueryAssignment', 'QueryReply', 'QueryTicket', 'QueryTopic'];

    foreach ($models as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});

it('has no legacy App\\Models\\{Club,Event,Form,Query}* references remaining inside app/Modules/Engagement after 4c', function (): void {
    $root = dirname(__DIR__, 3).'/app/Modules/Engagement';
    $legacyClasses = [
        'App\\Models\\Club', 'App\\Models\\ClubMember', 'App\\Models\\ClubMemberRoleHistory',
        'App\\Models\\Event', 'App\\Models\\EventParticipant',
        'App\\Models\\Form', 'App\\Models\\FormResponse', 'App\\Models\\FormResultVisibility',
        'App\\Models\\FormSection', 'App\\Models\\FormSurvey', 'App\\Models\\FormTarget', 'App\\Models\\FormVersion',
        'App\\Models\\QueryAssignment', 'App\\Models\\QueryReply', 'App\\Models\\QueryTicket', 'App\\Models\\QueryTopic',
    ];
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        foreach ($legacyClasses as $legacyClass) {
            if (preg_match('/'.preg_quote($legacyClass, '/').'\b/', $contents)) {
                $violations[] = $file->getPathname().' references '.$legacyClass;
            }
        }
    }

    expect($violations)->toBeEmpty(implode(', ', $violations));
});
