<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AcademicReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('academic-reports')->name('academic-reports.')->group(function () {
    Route::get('/', [AcademicReportController::class, 'index'])->name('index');
});
