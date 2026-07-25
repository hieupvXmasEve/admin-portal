<?php

declare(strict_types=1);

use Illuminate\Routing\Route;

it('routes lecturer workflows to their owning module controllers', function (): void {
    $expectedControllers = [
        'v1.lecturer.dashboard.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\DashboardController',
        'v1.lecturer.courses.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\CourseController',
        'v1.lecturer.courses.gradebook.show' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\GradebookController',
        'v1.lecturer.students.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\StudentController',
        'v1.lecturer.timetable.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\TimetableController',
        'v1.lecturer.attendance.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\AttendanceController',
        'v1.lecturer.assessments.index' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\AssessmentController',
        'v1.lecturer.assessments.report.overview' => 'App\\Modules\\Academic\\Delivery\\Http\\Api\\Lecturer\\AssessmentReportController',
        'v1.lecturer.notifications.index' => 'App\\Modules\\Notification\\Http\\Api\\Lecturer\\NotificationController',
    ];

    foreach ($expectedControllers as $routeName => $controller) {
        $route = app('router')->getRoutes()->getByName($routeName);

        expect($route)->toBeInstanceOf(Route::class);
        expect($route?->getControllerClass())->toBe($controller);
    }
});

it('does not retain legacy lecturer API runtime files', function (): void {
    foreach ([
        'app/Http/Controllers/Api/V1/Lecturer/AssessmentController.php',
        'app/Http/Controllers/Api/V1/Lecturer/AssessmentReportController.php',
        'app/Http/Controllers/Api/V1/Lecturer/CourseController.php',
        'app/Http/Controllers/Api/V1/Lecturer/DashboardController.php',
        'app/Http/Controllers/Api/V1/Lecturer/GradebookController.php',
        'app/Http/Controllers/Api/V1/Lecturer/StudentController.php',
        'app/Http/Controllers/Api/V1/Lecturer/TimetableController.php',
        'app/Services/AssessmentExportService.php',
        'app/Services/AssessmentReportService.php',
        'app/Services/V1/Lecturer/InvigilationDutyQuery.php',
        'app/Services/V1/Lecturer/LecturerCourseService.php',
        'app/Services/V1/Lecturer/LecturerDashboardService.php',
        'app/Services/V1/Lecturer/LecturerStudentService.php',
        'app/Services/V1/Lecturer/LecturerTimetableService.php',
    ] as $path) {
        expect(base_path($path))->not->toBeFile();
    }
});
