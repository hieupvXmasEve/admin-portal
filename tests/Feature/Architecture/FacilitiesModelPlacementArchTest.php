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

it('has deleted the app/Models shim for Building, Room, RoomBooking and RoomBookingAction', function (): void {
    $workspace = dirname(__DIR__, 3);

    // Room joined this list once Academic stopped reading rooms directly and
    // went through App\Shared\Contracts\Facilities\SpaceReferenceReader.
    // Building followed the same shape: the reader that used to live at
    // App\Modules\Academic\Support\CampusBuildingCountReader now lives at
    // App\Modules\Facilities\Support\EloquentCampusBuildingCountReader,
    // implementing App\Shared\Contracts\Academic\CampusBuildingCountReader.
    $sweptModels = ['Building', 'Room', 'RoomBooking', 'RoomBookingAction'];

    foreach ($sweptModels as $model) {
        $legacyPath = $workspace."/app/Models/{$model}.php";
        expect(file_exists($legacyPath))->toBeFalse("Expected shim to be deleted at app/Models/{$model}.php.");
        expect(class_exists("App\\Models\\{$model}"))->toBeFalse("App\\Models\\{$model} must not resolve by class_alias, subclass, or otherwise.");
    }
});
