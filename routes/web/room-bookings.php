<?php

use App\Http\Controllers\Web\RoomBookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Main booking routes (manual to ensure Ziggy names exist)
    Route::get('room-bookings', [RoomBookingController::class, 'index'])
        ->name('room-bookings.index');
    Route::get('room-bookings/create', [RoomBookingController::class, 'create'])
        ->name('room-bookings.create');
    Route::post('room-bookings', [RoomBookingController::class, 'store'])
        ->name('room-bookings.store');
    Route::get('room-bookings/{roomBooking}', [RoomBookingController::class, 'show'])
        ->name('room-bookings.show');
    Route::get('room-bookings/{roomBooking}/edit', [RoomBookingController::class, 'edit'])
        ->name('room-bookings.edit');
    Route::put('room-bookings/{roomBooking}', [RoomBookingController::class, 'update'])
        ->name('room-bookings.update');
    Route::delete('room-bookings/{roomBooking}', [RoomBookingController::class, 'destroy'])
        ->name('room-bookings.destroy');

    // My bookings (for current user)
    Route::get('room-bookings-my', [RoomBookingController::class, 'myBookings'])
        ->name('room-bookings.my-bookings');

    // Pending bookings (for approval)
    Route::get('room-bookings-pending', [RoomBookingController::class, 'pending'])
        ->name('room-bookings.pending');

    // Booking calendar view
    Route::get('room-bookings-calendar', [RoomBookingController::class, 'calendar'])
        ->name('room-bookings.calendar');

    // Booking logs/history
    Route::get('room-bookings-logs', [RoomBookingController::class, 'logs'])
        ->name('room-bookings.logs');

    // Approve/Reject/Cancel actions
    Route::post('room-bookings/{roomBooking}/approve', [RoomBookingController::class, 'approve'])
        ->name('room-bookings.approve');

    Route::post('room-bookings/{roomBooking}/reject', [RoomBookingController::class, 'reject'])
        ->name('room-bookings.reject');

    Route::post('room-bookings/{roomBooking}/cancel', [RoomBookingController::class, 'cancel'])
        ->name('room-bookings.cancel');

    // API endpoints for booking operations
    Route::prefix('api/room-bookings')->name('api.room-bookings.')->group(function () {
        Route::get('bookings', [RoomBookingController::class, 'apiGetBookings'])
            ->name('get-bookings');

        Route::post('check-conflicts', [RoomBookingController::class, 'apiCheckConflicts'])
            ->name('check-conflicts');
    });
});
