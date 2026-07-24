<?php

declare(strict_types=1);

it('retires legacy session and attendance runtime implementations after Delivery cutover', function (): void {
    $retiredPaths = [
        'app/Http/Controllers/Api/ClassSessionController.php',
        'app/Http/Controllers/Api/V1/Lecturer/AttendanceController.php',
        'app/Http/Controllers/Api/V1/Student/AttendanceController.php',
        'app/Http/Controllers/Web/AttendanceController.php',
        'app/Http/Controllers/Web/ClassSessionController.php',
        'app/Services/AttendanceService.php',
        'app/Services/ClassSessionService.php',
        'app/Services/V1/Lecturer/LecturerAttendanceService.php',
        'app/Services/V1/Student/AttendanceService.php',
        'routes/web/attendance.php',
        'routes/web/class-sessions.php',
    ];

    foreach ($retiredPaths as $path) {
        expect(base_path($path))->not->toBeFile();
    }
});

it('keeps preserved attendance routes pointed at Delivery-owned controllers', function (): void {
    $routeFiles = [
        base_path('app/Modules/Academic/routes/web.php'),
        base_path('routes/web/course-offerings.php'),
        base_path('routes/api/v1/student.php'),
        base_path('routes/api/v1/lecturer.php'),
    ];

    $legacyControllerNamespaces = [
        'App\\Http\\Controllers\\Api\\ClassSessionController',
        'App\\Http\\Controllers\\Api\\V1\\Lecturer\\AttendanceController',
        'App\\Http\\Controllers\\Api\\V1\\Student\\AttendanceController',
        'App\\Http\\Controllers\\Web\\AttendanceController',
        'App\\Http\\Controllers\\Web\\ClassSessionController',
    ];

    foreach ($routeFiles as $file) {
        $contents = file_get_contents($file) ?: '';

        foreach ($legacyControllerNamespaces as $legacyControllerNamespace) {
            expect($contents)->not->toContain($legacyControllerNamespace);
        }
    }
});

it('keeps Delivery attendance controllers as request adapters', function (): void {
    $controllers = [
        base_path('app/Modules/Academic/Delivery/Http/Api/ClassSessionController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Api/Lecturer/AttendanceController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Api/Student/AttendanceController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Web/AttendanceController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Web/ClassSessionController.php'),
    ];

    foreach ($controllers as $controller) {
        $contents = file_get_contents($controller) ?: '';

        expect($contents)
            ->not->toContain('->validate(')
            ->not->toMatch('/(?:Attendance|ClassSession|CourseOffering|Room|Lecture)::(?:query|where|find|with)\(/');
    }
});
