<?php

declare(strict_types=1);

/**
 * Placement guard for the Merchandise-owned store/redemption models. The
 * real classes must live under app/Modules/Merchandise/Models. All 7
 * app/Models shims for this batch (including GoldTransaction, swept in
 * plan 260815-1320 phase 3) have been deleted.
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

it('has deleted the app/Models shim for the 7 swept Merchandise models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // GoldTransaction joined the swept group once Engagement's event-reward
    // reclaim guard and audit-trail queries moved behind App\Services\GoldService
    // (plan 260815-1320 phase 3) instead of querying the model directly.
    $sweptModels = [
        'Merchandise',
        'MerchandiseImage',
        'MerchandiseVariant',
        'RedemptionOrder',
        'RedemptionOrderItem',
        'StockMovement',
        'GoldTransaction',
    ];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});
