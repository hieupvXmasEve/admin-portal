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

it('has no real (non-shim) Building, Room, RoomBooking, RoomBookingAction class under app/Models', function (): void {
    $workspace = dirname(__DIR__, 3);

    $models = ['Building', 'Room', 'RoomBooking', 'RoomBookingAction'];

    foreach ($models as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain at app/Models/{$model}.php.");

        $contents = file_get_contents($legacyPath) ?: '';
        expect($contents)
            ->toContain('class_alias(')
            ->not->toContain("class {$model} extends");
    }
});
