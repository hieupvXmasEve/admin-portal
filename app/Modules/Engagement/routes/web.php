<?php

declare(strict_types=1);

use App\Constants\ClubRoutes;
use App\Modules\Engagement\Http\Web\ClubController;
use App\Modules\Engagement\Http\Web\EventController;
use App\Modules\Engagement\Http\Web\EventReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('events/{event}/scanner', [EventController::class, 'scanner'])
        ->middleware('can:checkin_event')
        ->name('events.scanner');
    Route::get('events/{event}/manage-participants', [EventController::class, 'manageParticipants'])
        ->middleware('can:manage_participants')
        ->name('events.manage-participants');
    Route::post('events/{event}/publish', [EventController::class, 'publish'])
        ->middleware('can:publish_event')
        ->name('events.publish');
    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])
        ->middleware('can:cancel_event')
        ->name('events.cancel');
    Route::post('events/{event}/complete', [EventController::class, 'complete'])
        ->middleware('can:complete_event')
        ->name('events.complete');

    Route::prefix('events')->name('events.')->group(function (): void {
        Route::get('reports', [EventReportController::class, 'index'])->middleware('can:view_event')->name('reports');
        Route::get('reports/analytics', [EventReportController::class, 'analytics'])->middleware('can:view_event')->name('reports.analytics');
        Route::get('reports/export', [EventReportController::class, 'exportEvents'])->middleware('can:view_event')->name('reports.export');
        Route::get('{event}/stats', [EventReportController::class, 'eventStats'])->middleware('can:view_event')->name('stats');
        Route::get('{event}/export-participants', [EventReportController::class, 'exportParticipants'])->middleware('can:view_event')->name('export-participants');
        Route::get('reports/history', [EventReportController::class, 'eventHistory'])->middleware('can:view_event')->name('reports.history');
        Route::get('/list', [EventController::class, 'index'])->middleware('can:view_event')->name('index');
        Route::get('create', [EventController::class, 'create'])->middleware('can:create_event')->name('create');
        Route::get('create-manual', [EventController::class, 'createManual'])->middleware('can:create_event')->name('create-manual');
        Route::post('store', [EventController::class, 'store'])->middleware('can:create_event')->name('store');
        Route::post('store-manual', [EventController::class, 'storeManual'])->middleware('can:create_event')->name('store-manual');
        Route::get('{event}', [EventController::class, 'show'])->middleware('can:show_event')->name('show');
        Route::get('{event}/edit', [EventController::class, 'edit'])->middleware('can:update_event')->name('edit');
        Route::put('{event}', [EventController::class, 'update'])->middleware('can:update_event')->name('update');
        Route::delete('{event}', [EventController::class, 'destroy'])->middleware('can:delete_event')->name('destroy');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::get('clubs', [ClubController::class, 'index'])->middleware('can:view_clubs')->name(ClubRoutes::INDEX);
    Route::get('clubs/create', [ClubController::class, 'create'])->middleware('can:create_clubs')->name(ClubRoutes::CREATE);
    Route::post('clubs', [ClubController::class, 'store'])->middleware('can:create_clubs')->name(ClubRoutes::STORE);
    Route::get('clubs/{club}', [ClubController::class, 'show'])->middleware('can:view_clubs')->name(ClubRoutes::SHOW);
    Route::get('clubs/{club}/edit', [ClubController::class, 'edit'])->middleware('can:edit_clubs')->name(ClubRoutes::EDIT);
    Route::put('clubs/{club}', [ClubController::class, 'update'])->middleware('can:edit_clubs')->name(ClubRoutes::UPDATE);
    Route::delete('clubs/{club}', [ClubController::class, 'destroy'])->middleware('can:delete_clubs')->name(ClubRoutes::DESTROY);
    Route::post('clubs/{club}/assign-president', [ClubController::class, 'assignPresident'])->middleware('can:edit_clubs')->name(ClubRoutes::ASSIGN_PRESIDENT);
    Route::post('clubs/{club}/add-member', [ClubController::class, 'addMember'])->middleware('can:edit_clubs')->name(ClubRoutes::ADD_MEMBER);
    Route::get('api/clubs/{club}/students-for-assignment', [ClubController::class, 'studentsForAssignment'])->middleware('can:edit_clubs')->name(ClubRoutes::API_STUDENTS_FOR_ASSIGNMENT);
    Route::get('api/clubs/students-for-campus', [ClubController::class, 'studentsForCampus'])->middleware('can:create_clubs')->name(ClubRoutes::API_STUDENTS_FOR_CAMPUS);
});
