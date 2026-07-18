<?php

use App\Modules\Facilities\Http\Web\RoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('rooms', RoomController::class);
});
