<?php

declare(strict_types=1);

use App\Modules\Admissions\Http\Web\ApplicationGuardianController;
use App\Modules\Admissions\Http\Web\StudentApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('student-applications')->name('student-applications.')->group(function (): void {
    Route::get('/', [StudentApplicationController::class, 'index'])->middleware('can:view_student_application')->name('index');
    Route::get('/create', [StudentApplicationController::class, 'create'])->middleware('can:create_student_application')->name('create');
    Route::get('/export', [StudentApplicationController::class, 'export'])->middleware('can:view_student_application')->name('export');
    Route::post('/', [StudentApplicationController::class, 'store'])->middleware('can:create_student_application')->name('store');
    Route::get('/{studentApplication}', [StudentApplicationController::class, 'show'])->middleware('can:view_student_application')->name('show');
    Route::get('/{studentApplication}/documents', [StudentApplicationController::class, 'documents'])->middleware('can:view_student_application')->name('documents');
    Route::get('/{studentApplication}/edit', [StudentApplicationController::class, 'edit'])->middleware('can:edit_student_application')->name('edit');
    Route::put('/{studentApplication}', [StudentApplicationController::class, 'update'])->middleware('can:edit_student_application')->name('update');
    Route::delete('/{studentApplication}', [StudentApplicationController::class, 'destroy'])->middleware('can:delete_student_application')->name('destroy');
    Route::post('/{studentApplication}/approve', [StudentApplicationController::class, 'approve'])->middleware('can:approve,studentApplication')->name('approve');
    Route::post('/{studentApplication}/reject', [StudentApplicationController::class, 'reject'])->middleware('can:reject,studentApplication')->name('reject');
    Route::post('/{studentApplication}/revoke', [StudentApplicationController::class, 'revoke'])->middleware('can:revoke,studentApplication')->name('revoke');
    Route::post('/{studentApplication}/guardians', [ApplicationGuardianController::class, 'store'])->middleware('can:edit_student_application')->name('guardians.store');
    Route::put('/{studentApplication}/guardians/{guardian}', [ApplicationGuardianController::class, 'update'])->middleware('can:edit_student_application')->name('guardians.update');
    Route::delete('/{studentApplication}/guardians/{guardian}', [ApplicationGuardianController::class, 'destroy'])->middleware('can:edit_student_application')->name('guardians.destroy');
});
