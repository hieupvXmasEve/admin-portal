<?php

declare(strict_types=1);

/**
 * Placement guard for the Facilities-owned room/booking models. The real
 * classes must live under app/Modules/Facilities/Models; the app/Models copy
 * is a class_alias shim kept only for cross-module backward compatibility.
 */
it('keeps Building, Room, RoomBooking, RoomBookingAction inside Facilities module', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['Building', 'Room', 'RoomBooking', 'RoomBookingAction'];

    foreach ($models as $model) {
        $modulePath = $workspace."/app/Modules/Facilities/Models/{$model}.php";
        expect(file_exists($modulePath))->toBeTrue("Expected {$model} to live under app/Modules/Facilities/Models.");

        $fqcn = "App\\Modules\\Facilities\\Models\\{$model}";
        expect(class_exists($fqcn))->toBeTrue();

        $reflection = new ReflectionClass($fqcn);
        expect($reflection->getFileName())->toBe(realpath($modulePath));
    }
});

it('has deleted the app/Models shim for RoomBooking and RoomBookingAction', function (): void {
    $workspace = dirname(__DIR__, 3);

    $sweptModels = ['RoomBooking', 'RoomBookingAction'];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});

it('has no real (non-shim) Building/Room model class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    // Room and Building stay blocked: an Academic -> Facilities cross-module
    // read still resolves them through this shim, and rewriting either
    // caller to the canonical namespace would trip the zero-tolerance
    // cross_context_concrete_imports boundary rule.
    $blockedModels = ['Building', 'Room'];

    foreach ($blockedModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});
