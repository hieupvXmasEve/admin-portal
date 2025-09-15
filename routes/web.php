<?php

use App\Http\Controllers\Api\ElectiveController;
use App\Http\Controllers\Web\CurriculumVersionController;
use App\Http\Controllers\Web\SelectCampus;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Dashboard chart API endpoints
// Route::middleware(['web', 'verified'])->prefix('api/dashboard')->name('api.dashboard.')->group(function () {
//     Route::get('student-distribution', [\App\Http\Controllers\Web\DashboardController::class, 'studentDistribution'])->name('student-distribution');
//     Route::get('enrollment-growth', [\App\Http\Controllers\Web\DashboardController::class, 'enrollmentGrowth'])->name('enrollment-growth');
//     Route::get('academic-standing', [\App\Http\Controllers\Web\DashboardController::class, 'academicStanding'])->name('academic-standing');
//     Route::get('graduation-rate', [\App\Http\Controllers\Web\DashboardController::class, 'graduationRate'])->name('graduation-rate');
// });

Route::middleware(['auth'])->group(callback: function () {
    Route::get('select-campus', [SelectCampus::class, 'index'])->name('select-campus.index');
    Route::post('select-campus/set-current', [SelectCampus::class, 'setCurrentCampus'])->name('select-campus.set-current');
    Route::post('select-campus/change', [SelectCampus::class, 'changeCampus'])->name('select-campus.change');
});

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
require __DIR__ . '/web/campuses.php';
require __DIR__ . '/web/rooms.php';
require __DIR__ . '/web/lectures.php';
require __DIR__ . '/web/teaching-assignments.php';
require __DIR__ . '/web/student-management.php';
require __DIR__ . '/web/class-sessions.php';
require __DIR__ . '/web/attendance.php';
require __DIR__ . '/web/class-schedule.php';
require __DIR__ . '/web/student-application.php';
require __DIR__ . '/web/systems.php';
require __DIR__ . '/web/email-monitoring.php';
require __DIR__ . '/web/syllabus-templates.php';
require __DIR__ . '/web/forms.php';
