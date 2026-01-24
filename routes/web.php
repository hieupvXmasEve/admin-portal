<?php

use App\Http\Controllers\Api\ElectiveController;
use App\Http\Controllers\Web\CurriculumVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Identity routes are now registered via IdentityServiceProvider from app/Modules/Identity/routes/web.php


Route::middleware(['auth', 'verified'])->group(function () {
    // Curriculum Version routes with permission middleware
    Route::resource('curriculum-versions', CurriculumVersionController::class)->middleware('can:view_curriculum_version');
    Route::get('/curriculum-versions/{curriculumVersion}/electives', [CurriculumVersionController::class, 'electiveManagement'])
        ->middleware('can:view_curriculum_version')
        ->name('curriculum_version.electives');

    // API routes for elective management
    Route::prefix('api')->name('api.')->group(function () {
        // Get available electives for a curriculum version
        Route::get('/curriculum-versions/{curriculumVersion}/available-electives', [ElectiveController::class, 'getAvailableElectives'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum_version.available-electives');

        // Get elective slots for a curriculum version
        Route::get('/curriculum-versions/{curriculumVersion}/elective-slots', [ElectiveController::class, 'getElectiveSlots'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum_version.elective-slots');

        // Update an elective slot
        Route::put('/curriculum-units/{curriculumUnit}/update-elective', [ElectiveController::class, 'updateElectiveSlot'])
            ->middleware('can:edit_curriculum_version')
            ->name('curriculum-units.update-elective');

        // Get unit details for elective selection
        Route::get('/units/{unit}/details', [ElectiveController::class, 'getUnitDetails'])
            ->middleware('can:view_unit')
            ->name('units.details');

        // Get elective recommendations for a curriculum unit
        Route::get('/curriculum-units/{curriculumUnit}/recommendations', [ElectiveController::class, 'getElectiveRecommendations'])
            ->middleware('can:view_curriculum_version')
            ->name('curriculum-units.recommendations');
    });
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
});
// Review code and optimize
require __DIR__ . '/web/campuses.php';

require __DIR__ . '/web/rooms.php';
require __DIR__ . '/web/settings.php';
require __DIR__ . '/web/auth.php';
require __DIR__ . '/web/user.php';
require __DIR__ . '/web/role.php';
require __DIR__ . '/web/semester.php';
require __DIR__ . '/web/units.php';
require __DIR__ . '/web/programs.php';
require __DIR__ . '/web/specializations.php';
require __DIR__ . '/web/curriculum.php';
require __DIR__ . '/web/course-offerings.php';
require __DIR__ . '/web/course-registrations.php';
require __DIR__ . '/web/lectures.php';
// require __DIR__ . '/web/student.php';
require __DIR__ . '/web/class-sessions.php';
require __DIR__ . '/web/attendance.php';
require __DIR__ . '/web/course-statistics.php';
require __DIR__ . '/web/failed-students.php';
require __DIR__ . '/web/class-schedule.php';
require __DIR__ . '/web/student-application.php';
require __DIR__ . '/web/systems.php';
require __DIR__ . '/web/email-monitoring.php';
require __DIR__ . '/web/syllabus-templates.php';
require __DIR__ . '/web/forms.php';
require __DIR__ . '/web/clubs.php';
require __DIR__ . '/web/events.php';
require __DIR__ . '/web/modules.php';
require __DIR__ . '/web/scholarships.php';
require __DIR__ . '/web/canvas.php';
require __DIR__ . '/web/student-scholarships.php';
require __DIR__ . '/web/tuition-plans.php';
require __DIR__ . '/web/billing-cycles.php';
require __DIR__ . '/web/wallets.php';
require __DIR__ . '/web/vouchers.php';
// require __DIR__ . '/web/surveys.php';
require __DIR__ . '/web/room-bookings.php';
require __DIR__ . '/web/departments.php';
require __DIR__ . '/web/notifications.php';
require __DIR__ . '/web/academic.php';

