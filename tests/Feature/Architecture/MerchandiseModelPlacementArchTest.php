<?php

declare(strict_types=1);

/**
 * Placement guard for the Merchandise-owned store/redemption models. The
 * real classes must live under app/Modules/Merchandise/Models; the
 * app/Models copy is a class_alias shim kept only for cross-module backward
 * compatibility.
 */
it('keeps the 7 Merchandise models inside the Merchandise module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = [
        'Merchandise',
        'MerchandiseImage',
        'MerchandiseVariant',
        'RedemptionOrder',
        'RedemptionOrderItem',
        'StockMovement',
        'GoldTransaction',
    ];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Merchandise/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Merchandise/Models.");

        $fqcn = "App\\Modules\\Merchandise\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has deleted the app/Models shim for the 6 swept Merchandise models', function (): void {
    $workspace = dirname(__DIR__, 3);

    $sweptModels = [
        'Merchandise',
        'MerchandiseImage',
        'MerchandiseVariant',
        'RedemptionOrder',
        'RedemptionOrderItem',
        'StockMovement',
    ];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});

it('has no real (non-shim) GoldTransaction model class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // GoldTransaction stays blocked: an Engagement -> Merchandise
    // cross-module read still resolves it through this shim, and rewriting
    // that caller to the canonical namespace would trip the zero-tolerance
    // cross_context_concrete_imports boundary rule.
    $legacyPath = $workspace.'/app/Models/GoldTransaction.php';
    expect(file_exists($legacyPath))->toBeTrue('Expected shim to remain at app/Models/GoldTransaction.php.');

    $contents = file_get_contents($legacyPath) ?: '';
    expect($contents)
        ->toContain('class_alias(')
        ->not->toContain('class GoldTransaction extends');
});
