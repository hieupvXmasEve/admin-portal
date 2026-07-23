<?php

declare(strict_types=1);

use App\Modules\Facilities\Http\Web\BuildingController;
use App\Modules\Facilities\Http\Web\RoomBookingController;
use App\Modules\Facilities\Http\Web\RoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::prefix('buildings')->name('buildings.')->group(function () {
        Route::get('/', [BuildingController::class, 'index'])
            ->middleware('can:view_building')
            ->name('index');

        Route::get('/api', [BuildingController::class, 'api'])
            ->name('api');

        Route::get('/{building}', [BuildingController::class, 'show'])
            ->middleware('can:view_building')
            ->name('show');
    });

    Route::prefix('campuses/{campus}/buildings')->name('campuses.buildings.')->group(function () {
        Route::get('/create', [BuildingController::class, 'create'])
            ->middleware('can:create_building')
            ->name('create');

        Route::post('/', [BuildingController::class, 'store'])
            ->middleware('can:create_building')
            ->name('store');

        Route::get('/{building}/edit', [BuildingController::class, 'edit'])
            ->middleware('can:edit_building')
            ->name('edit');

        Route::put('/{building}', [BuildingController::class, 'update'])
            ->middleware('can:edit_building')
            ->name('update');

        Route::delete('/{building}', [BuildingController::class, 'destroy'])
            ->middleware('can:delete_building')
            ->name('destroy');
    });
});

Route::middleware(['web', 'auth', 'verified', 'campus.selected'])->group(function () {
    Route::resource('rooms', RoomController::class);

    Route::get('room-bookings', [RoomBookingController::class, 'index'])
        ->name('room-bookings.index');
    Route::get('room-bookings/create', [RoomBookingController::class, 'create'])
        ->name('room-bookings.create');
    Route::post('room-bookings', [RoomBookingController::class, 'store'])
        ->name('room-bookings.store');

    Route::get('room-bookings/availability', [RoomBookingController::class, 'availability'])
        ->name('room-bookings.availability');

    Route::get('room-bookings-my', [RoomBookingController::class, 'myBookings'])
        ->name('room-bookings.my-bookings');
    Route::get('room-bookings-pending', [RoomBookingController::class, 'pending'])
        ->name('room-bookings.pending');
    Route::get('room-bookings-calendar', [RoomBookingController::class, 'calendar'])
        ->name('room-bookings.calendar');
    Route::get('room-bookings-logs', [RoomBookingController::class, 'logs'])
        ->name('room-bookings.logs');

    Route::get('room-bookings/{roomBooking}', [RoomBookingController::class, 'show'])
        ->name('room-bookings.show');
    Route::get('room-bookings/{roomBooking}/edit', [RoomBookingController::class, 'edit'])
        ->name('room-bookings.edit');
    Route::put('room-bookings/{roomBooking}', [RoomBookingController::class, 'update'])
        ->name('room-bookings.update');
    Route::delete('room-bookings/{roomBooking}', [RoomBookingController::class, 'destroy'])
        ->name('room-bookings.destroy');

    Route::post('room-bookings/{roomBooking}/approve', [RoomBookingController::class, 'approve'])
        ->name('room-bookings.approve');
    Route::post('room-bookings/{roomBooking}/reject', [RoomBookingController::class, 'reject'])
        ->name('room-bookings.reject');
    Route::post('room-bookings/{roomBooking}/cancel', [RoomBookingController::class, 'cancel'])
        ->name('room-bookings.cancel');

    Route::prefix('api/room-bookings')->name('api.room-bookings.')->group(function () {
        Route::get('bookings', [RoomBookingController::class, 'apiGetBookings'])
            ->name('get-bookings');
        Route::post('check-conflicts', [RoomBookingController::class, 'apiCheckConflicts'])
            ->name('check-conflicts');
        Route::post('preview-series', [RoomBookingController::class, 'apiPreviewSeries'])
            ->name('preview-series');
    });
});

Route::middleware(['web', 'auth', 'verified', 'campus.selected', 'can:view_room'])
    ->get('api/rooms', [RoomController::class, 'apiIndex'])
    ->name('api.admin.rooms.api-index');
