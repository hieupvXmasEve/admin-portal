<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Web\StudentController as WebStudentController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\SyllabusController;
use App\Http\Controllers\Api\ClassSessionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::name('api.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/login/google', [AuthController::class, 'loginWithGoogle'])->name('auth.login.google');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});


// Admin API routes (for internal use)
Route::name('api.admin.')->middleware(['auth:sanctum'])->group(function () {
    // Campus Management
    Route::apiResource('campuses', CampusController::class);

    // Building Management
    Route::apiResource('buildings', BuildingController::class);

    // User Management
    Route::apiResource('users', UserController::class);

    // Role Management
    Route::apiResource('roles', RoleController::class);

    // Unit Management
    Route::apiResource('units', UnitController::class);

    // Program Management
    Route::apiResource('programs', ProgramController::class);

    // Syllabus Management
    Route::prefix('units/{unit}/syllabus')->name('syllabus.')->group(function () {
        Route::get('/', [SyllabusController::class, 'index'])->name('index');
        Route::get('/active', [SyllabusController::class, 'active'])->name('active');
        Route::get('/{syllabus}', [SyllabusController::class, 'show'])->name('show');
    });

    // Student Management
    Route::get('/students/search', [WebStudentController::class, 'apiSearch'])->name('students.search');
    Route::post('/students/by-ids', [WebStudentController::class, 'getByIds'])->name('students.by-ids');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/stats', [StudentController::class, 'stats'])->name('students.stats');
    Route::get('/students/{student}', [WebStudentController::class, 'show'])->name('students.show');
    Route::put('/students/{student}', [WebStudentController::class, 'update'])->name('students.update');
    Route::patch('/students/{student}', [WebStudentController::class, 'update'])->name('students.patch');
    Route::delete('/students/{student}', [WebStudentController::class, 'destroy'])->name('students.destroy');

    // Registration Management
    Route::get('/registrations', [RegistrationController::class, 'adminIndex'])->name('registrations.index');
    Route::post('/registrations/{registration}/override', [RegistrationController::class, 'adminOverride'])->name('registrations.override');
    Route::get('/registrations/reports', [RegistrationController::class, 'reports'])->name('registrations.reports');

    // Class Sessions Management
    Route::apiResource('class-sessions', ClassSessionController::class);
    Route::get('/class-sessions/{classSession}/attendances', [ClassSessionController::class, 'attendances'])->name('class-sessions.attendances');
    Route::post('/class-sessions/{classSession}/start', [ClassSessionController::class, 'start'])->name('class-sessions.start');
    Route::post('/class-sessions/{classSession}/complete', [ClassSessionController::class, 'complete'])->name('class-sessions.complete');
    Route::post('/class-sessions/{classSession}/cancel', [ClassSessionController::class, 'cancel'])->name('class-sessions.cancel');
    Route::post('/class-sessions/{classSession}/generate-attendance', [ClassSessionController::class, 'generateAttendance'])->name('class-sessions.generate-attendance');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
})->name('api.health');

// require __DIR__.'/api/public.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/student.php';
require __DIR__ . '/api/lecture.php';
