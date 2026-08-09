<?php

declare(strict_types=1);

it('keeps Facilities availability off Delivery persistence and implementation types', function (): void {
    $facilitiesRoot = dirname(__DIR__, 3).'/app/Modules/Facilities';
    $modelsRoot = $facilitiesRoot.'/Models';
    $forbiddenImports = [
        'App\\Models\\ClassSession',
        'App\\Models\\ExamResitSession',
        'App\\Models\\ExamRoomSlot',
        'App\\Models\\Campus',
        'App\\Modules\\Academic\\',
    ];
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($facilitiesRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        // Models/ is exempt from the App\Models\Campus ban only: Eloquent FK
        // relations (belongsTo(Campus::class)) are a persistence concern,
        // not the business-logic coupling this guard targets. Every other
        // Facilities layer still must go through CampusReferenceReader, and
        // Models/ still cannot reach into Delivery/Academic internals.
        $forbiddenForFile = str_starts_with($file->getPathname(), $modelsRoot.'/')
            ? array_diff($forbiddenImports, ['App\\Models\\Campus'])
            : $forbiddenImports;

        $contents = file_get_contents($file->getPathname()) ?: '';
        foreach ($forbiddenForFile as $forbiddenImport) {
            if (str_contains($contents, $forbiddenImport)) {
                $violations[] = $file->getPathname().' uses '.$forbiddenImport;
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Facilities must use the AcademicSpaceOccupancyReader contract rather than Delivery persistence or services.',
    );
});

it('keeps Delivery exam scheduling on the Facilities reservation contract', function (): void {
    $action = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Delivery/Actions/CreateExamRoomSlotAction.php') ?: '';
    $controller = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Delivery/Http/Web/ExamScheduleController.php') ?: '';
    $request = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Delivery/Http/Requests/ExamResit/StoreExamRoomSlotRequest.php') ?: '';

    expect($action)->toContain('App\\Shared\\Contracts\\Facilities\\SpaceReservationContract')
        ->not->toContain('App\\Models\\Room')
        ->not->toContain('App\\Modules\\Academic\\Services\\ExamScheduleConflictChecker')
        ->and($controller)->toContain('App\\Shared\\Contracts\\Facilities\\SpaceReferenceReader')
        ->not->toContain('App\\Models\\Room')
        ->and($request)->not->toContain('exists:rooms');
});

it('keeps Delivery course-offering room changes on Facilities reference and availability contracts', function (): void {
    $action = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Academic/Delivery/Actions/ChangeCourseOfferingRoomAction.php') ?: '';

    expect($action)
        ->toContain('App\\Shared\\Contracts\\Facilities\\SpaceReferenceReader')
        ->toContain('App\\Shared\\Contracts\\Facilities\\SpaceAvailabilityReader')
        ->not->toContain('App\\Models\\Room');
});

it('serves building and room URLs from the Facilities owner', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 3).'/app/Modules/Facilities/routes/web.php') ?: '';

    expect($routes)->toContain('App\\Modules\\Facilities\\Http\\Web\\BuildingController')
        ->not->toContain('App\\Modules\\Academic\\Http\\Web\\BuildingController')
        ->and($routes)->toContain('App\\Modules\\Facilities\\Http\\Web\\RoomController');
});

it('keeps the supported room and room-booking path entirely in Facilities', function (): void {
    $root = dirname(__DIR__, 3);
    $facilitiesController = file_get_contents($root.'/app/Modules/Facilities/Http/Web/RoomBookingController.php') ?: '';
    $facilitiesRoomController = file_get_contents($root.'/app/Modules/Facilities/Http/Web/RoomController.php') ?: '';
    $facilitiesRoutes = file_get_contents($root.'/app/Modules/Facilities/routes/web.php') ?: '';

    expect($facilitiesController)
        ->not->toContain('App\\Services\\RoomBookingService')
        ->and($facilitiesRoomController)
        ->not->toContain('App\\Http\\Controllers\\Web\\RoomController')
        ->and($facilitiesRoutes)
        ->toContain("Route::resource('rooms'")
        ->and(file_exists($root.'/app/Services/RoomService.php'))->toBeFalse()
        ->and(file_exists($root.'/app/Services/RoomBookingService.php'))->toBeFalse()
        ->and(file_exists($root.'/app/Http/Controllers/Web/RoomController.php'))->toBeFalse()
        ->and(file_exists($root.'/app/Http/Controllers/Web/RoomBookingController.php'))->toBeFalse()
        ->and(file_exists($root.'/routes/web/rooms.php'))->toBeFalse()
        ->and(file_exists($root.'/routes/web/room-bookings.php'))->toBeFalse();
});
