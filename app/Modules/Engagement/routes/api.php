<?php

declare(strict_types=1);

use App\Modules\Engagement\Http\Api\Admin\EventCheckinController;
use App\Modules\Engagement\Http\Api\Admin\EventParticipantController;
use App\Modules\Engagement\Http\Api\Student\ClubController;
use App\Modules\Engagement\Http\Api\Student\ClubManagementController;
use App\Modules\Engagement\Http\Api\Student\EventController;
use App\Modules\Engagement\Http\Api\Student\FormController;
use App\Modules\Engagement\Http\Api\Student\QueryTicketController;
use App\Modules\Engagement\Http\Web\Admin\FormController as AdminFormController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->name('api.admin.')->group(function (): void {
    Route::prefix('forms')->name('api.forms.')->group(function (): void {
        Route::get('/', [AdminFormController::class, 'index'])->name('index');
        Route::post('/', [AdminFormController::class, 'store'])->name('store');
        Route::get('/metadata', [AdminFormController::class, 'metadata'])->name('metadata');
        Route::get('/available', [AdminFormController::class, 'available'])->name('available');
        Route::prefix('{form}')->group(function (): void {
            Route::get('/', [AdminFormController::class, 'show'])->name('show');
            Route::put('/', [AdminFormController::class, 'update'])->name('update');
            Route::delete('/', [AdminFormController::class, 'destroy'])->name('destroy');
            Route::post('/clone', [AdminFormController::class, 'clone'])->name('clone');
            Route::post('/archive', [AdminFormController::class, 'archive'])->name('archive');
            Route::post('/restore', [AdminFormController::class, 'restore'])->name('restore');
            Route::get('/statistics', [AdminFormController::class, 'statistics'])->name('statistics');
            Route::post('/versions/{version}/publish', [AdminFormController::class, 'publish'])->name('version.publish');
            Route::post('/targets', [AdminFormController::class, 'createTarget'])->name('targets.store');
        });
    });

    Route::prefix('events')->name('events.')->group(function (): void {
        Route::post('/search-student', [EventCheckinController::class, 'searchStudent'])->name('search-student');
        Route::post('/checkin', [EventCheckinController::class, 'checkinStudent'])->name('checkin');
        Route::get('/{event}/participants', [EventCheckinController::class, 'getParticipants'])->name('participants');
        Route::get('/{event}/statistics', [EventCheckinController::class, 'getStatistics'])->name('statistics');
        Route::prefix('{event}/manual-participants')->name('manual-participants.')->group(function (): void {
            Route::get('/filter-options', [EventParticipantController::class, 'getFilterOptions'])->name('filter-options');
            Route::post('/search-students', [EventParticipantController::class, 'searchStudents'])->name('search-students');
            Route::post('/add', [EventParticipantController::class, 'addParticipants'])->name('add');
            Route::get('/list', [EventParticipantController::class, 'getParticipants'])->name('list');
            Route::put('/bulk-update-status', [EventParticipantController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
            Route::delete('/remove', [EventParticipantController::class, 'removeParticipants'])->name('remove');
            Route::get('/statistics', [EventParticipantController::class, 'getStatistics'])->name('statistics');
        });
    });
});

Route::prefix('v1/student')->name('v1.student.')->middleware([
    'auth:sanctum',
    'api.logging',
    'api.actor:student_or_parent',
])->group(function (): void {
    Route::middleware(['either:parent.student.access,student.api.auth'])->group(function (): void {
        Route::prefix('clubs')->name('clubs.')->group(function (): void {
            Route::get('/', [ClubController::class, 'index'])->name('index');
            Route::get('/my-memberships', [ClubController::class, 'myMemberships'])->name('my-memberships');
            Route::get('/{club}', [ClubController::class, 'show'])->name('show');
            Route::post('/{club}/apply', [ClubController::class, 'apply'])->name('apply');
            Route::get('/{club}/manage', [ClubManagementController::class, 'managementDashboard'])->name('manage');
            Route::put('/{club}', [ClubManagementController::class, 'update'])->name('update');
            Route::get('/{club}/members', [ClubManagementController::class, 'members'])->name('members');
            Route::put('/{club}/members/{member}/approve', [ClubManagementController::class, 'approveMember'])->name('approve-member');
            Route::put('/{club}/members/{member}/reject', [ClubManagementController::class, 'rejectMember'])->name('reject-member');
            Route::put('/{club}/members/{member}/role', [ClubManagementController::class, 'updateMemberRole'])->name('update-member-role');
        });

        Route::prefix('events')->name('events.')->group(function (): void {
            Route::get('/', [EventController::class, 'index'])->name('index');
            Route::get('/{event}', [EventController::class, 'show'])->name('show');
            Route::post('/{event}/register', [EventController::class, 'register'])->name('register');
            Route::delete('/{event}/register', [EventController::class, 'unregister'])->name('unregister');
            Route::get('/my/participations', [EventController::class, 'myEvents'])->name('my-events');
            Route::get('/my/{event}', [EventController::class, 'myEventDetails'])->name('my-event-details');
        });

        Route::prefix('forms')->name('forms.')->group(function (): void {
            Route::get('/', [FormController::class, 'index'])->name('index');
            Route::prefix('query')->name('query.')->group(function (): void {
                Route::get('/runs', [FormController::class, 'queryRuns'])->name('runs');
                Route::get('/{form}', [FormController::class, 'show'])->name('show');
                Route::post('/{form}/submit', [FormController::class, 'submit'])->name('submit');
            });
            Route::prefix('surveys')->name('surveys.')->group(function (): void {
                Route::get('/pending', [FormController::class, 'pending'])->name('pending');
                Route::get('/{form}', [FormController::class, 'show'])->name('show');
                Route::post('/{form}/submit', [FormController::class, 'submit'])->name('submit');
            });
        });

        Route::prefix('queries')->name('queries.')->group(function (): void {
            Route::get('/', [QueryTicketController::class, 'index'])->name('index');
            Route::get('/{ticket}', [QueryTicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/replies', [QueryTicketController::class, 'storeReply'])->name('replies.store');
        });
    });
});
