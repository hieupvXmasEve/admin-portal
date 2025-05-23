<?php

use App\Http\Controllers\UserController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'users',
        controller: UserController::class,
        module: 'users'
    );
});
