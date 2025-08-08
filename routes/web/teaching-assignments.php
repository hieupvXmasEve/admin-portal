<?php

declare(strict_types=1);

use App\Constants\TeachingAssignmentRoutes;
use App\Http\Controllers\Web\TeachingAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Teaching Assignments main routes
    //    Route::get('teaching-assignments', [TeachingAssignmentController::class, 'index'])
    //        ->middleware('can:view_teaching_assignment')
    //        ->name(TeachingAssignmentRoutes::INDEX);
    //
    //    // API routes for teaching assignments
    //    Route::prefix('api/teaching-assignments')->name('api.teaching-assignments.')->group(function () {
    //        Route::get('/', [TeachingAssignmentController::class, 'index'])
    //            ->middleware('can:view_teaching_assignment')
    //            ->name('index');
    //
    //        Route::post('/assign', [TeachingAssignmentController::class, 'assign'])
    //            ->middleware('can:assign_lecturer')
    //            ->name('assign');
    //
    //        Route::delete('/{courseOfferingId}/unassign', [TeachingAssignmentController::class, 'unassign'])
    //            ->middleware('can:unassign_lecturer')
    //            ->name('unassign');
    //
    //        Route::get('/{courseOfferingId}/available-lecturers', [TeachingAssignmentController::class, 'availableLecturers'])
    //            ->middleware('can:manage_teaching_assignment')
    //            ->name('available-lecturers');
    //
    //        Route::post('/check-conflicts', [TeachingAssignmentController::class, 'checkConflicts'])
    //            ->middleware('can:manage_teaching_assignment')
    //            ->name('check-conflicts');
    //
    //        Route::post('/export', [TeachingAssignmentController::class, 'export'])
    //            ->middleware('can:export_teaching_assignment')
    //            ->name('export');
    //    });
});
