<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Web\SelectCampus;
use App\Http\Controllers\Web\CurriculumVersionController;
use App\Http\Controllers\Api\ElectiveController;


Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware(['auth'])->group(callback: function () {
    Route::get('select-campus', [SelectCampus::class, 'index'])->name('select-campus.index');
    Route::post('select-campus/set-current', [SelectCampus::class, 'setCurrentCampus'])->name('select-campus.set-current');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Curriculum Version routes với elective management
    Route::resource('curriculum-versions', CurriculumVersionController::class);
    Route::get('/curriculum-versions/{curriculumVersion}/electives', [CurriculumVersionController::class, 'electiveManagement'])
        ->name('curriculum_version.electives');

    // API routes for elective management
    Route::prefix('api')->name('api.')->group(function () {
        // Get available electives for a curriculum version
        Route::get('/curriculum-versions/{curriculumVersion}/available-electives', [ElectiveController::class, 'getAvailableElectives'])
            ->name('curriculum_version.available-electives');

        // Get elective slots for a curriculum version
        Route::get('/curriculum-versions/{curriculumVersion}/elective-slots', [ElectiveController::class, 'getElectiveSlots'])
            ->name('curriculum_version.elective-slots');

        // Update an elective slot
        Route::put('/curriculum-units/{curriculumUnit}/update-elective', [ElectiveController::class, 'updateElectiveSlot'])
            ->name('curriculum-units.update-elective');

        // Get unit details for elective selection
        Route::get('/units/{unit}/details', [ElectiveController::class, 'getUnitDetails'])
            ->name('units.details');

        // Get elective recommendations for a curriculum unit
        Route::get('/curriculum-units/{curriculumUnit}/recommendations', [ElectiveController::class, 'getElectiveRecommendations'])
            ->name('curriculum-units.recommendations');
    });
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});
require __DIR__ . '/web/settings.php';
require __DIR__ . '/web/auth.php';
require __DIR__ . '/web/user.php';
require __DIR__ . '/web/role.php';
require __DIR__ . '/web/semester.php';
require __DIR__ . '/web/units.php';
require __DIR__ . '/web/syllabus.php';
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
