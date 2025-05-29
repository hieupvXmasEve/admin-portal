<?php

use App\Http\Controllers\SemesterController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'semesters',
        controller: SemesterController::class,
        module: 'semesters'
    );
});
