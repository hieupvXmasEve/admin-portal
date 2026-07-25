<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AcademicReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('academic-reports')->name('academic-reports.')->middleware('can:view_academic_report')->group(function () {
    Route::get('/', [AcademicReportController::class, 'index'])->name('index');
});
