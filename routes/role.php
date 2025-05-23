<?php

use App\Http\Controllers\RoleController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'roles',
        controller: RoleController::class,
        module: 'roles'
    );
});
