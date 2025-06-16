<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SelectCampus;
use App\Http\Controllers\CurriculumVersionController;
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
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/user.php';
require __DIR__ . '/role.php';
require __DIR__ . '/semester.php';
require __DIR__ . '/units.php';
require __DIR__ . '/syllabus.php';
require __DIR__ . '/programs.php';
require __DIR__ . '/specializations.php';
require __DIR__ . '/curriculum.php';
require __DIR__ . '/students.php';
require __DIR__ . '/course-offerings.php';
require __DIR__ . '/course-registrations.php';
