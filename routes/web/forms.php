<?php

use App\Http\Controllers\QueryTicketController;
use App\Http\Controllers\Web\Admin\FormTargetController;
use App\Http\Controllers\Web\Admin\QueryController;
use App\Http\Controllers\Web\Admin\SurveyResultController;
use App\Http\Controllers\Web\FormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Forms Routes
|--------------------------------------------------------------------------
|
| Routes for the feedback/survey/query forms system
|
*/

// Admin/Staff form management routes
Route::middleware(['auth'])->group(function () {
    Route::prefix('forms')->name('forms.')->group(function () {
        // Admin form management
        Route::prefix('admin')->name('admin.')->group(function () {
            // Form Runs Management
            Route::prefix('runs')->name('runs.')->group(function () {
                Route::get('/', [FormTargetController::class, 'index'])->name('index');
                Route::get('/create', [FormTargetController::class, 'create'])->name('create');
                Route::post('/', [FormTargetController::class, 'store'])->name('store');
                Route::post('/{target}/activate', [FormTargetController::class, 'activate'])->name('activate');
                Route::post('/{target}/close', [FormTargetController::class, 'close'])->name('close');
            });

            // Staff Query Inbox (New)
            Route::prefix('inbox')->name('inbox.')->group(function () {
                Route::get('/', [QueryController::class, 'index'])->name('index');
                Route::get('/{response}', [QueryController::class, 'show'])->name('show');
                Route::post('/{response}/assign', [QueryController::class, 'assign'])->name('assign');
                Route::post('/{response}/reply', [QueryController::class, 'reply'])->name('reply');
                Route::post('/{response}/status', [QueryController::class, 'updateStatus'])->name('status.update');
            });
            // Staff survey inbox
            Route::prefix('results')->name('results.')->group(function () {
                // List survey results
                Route::get('/', [SurveyResultController::class, 'index'])
                    ->name('index')
                    ->middleware('can:view_survey');
                // Program stats (must be before /{target} routes)
                Route::get('/stats', [SurveyResultController::class, 'stats'])
                    ->name('stats')
                    ->middleware('can:view_survey_results_aggregate');
                // Aggregate survey results
                Route::get('/{target}/aggregate', [SurveyResultController::class, 'aggregate'])
                    ->name('aggregate')
                    ->middleware('can:view_survey_results_aggregate');
                Route::get('/{target}/aggregate/download', [SurveyResultController::class, 'downloadAggregate'])
                    ->name('aggregate.download')
                    ->middleware('can:view_survey_results_aggregate');
                // Raw survey results
                Route::get('/{target}/raw', [SurveyResultController::class, 'raw'])
                    ->name('raw')
                    ->middleware('can:view_survey_results_raw');
            });
            Route::get('/', [FormController::class, 'index'])->name('index');
            Route::get('/create', [FormController::class, 'create'])->name('create');
            Route::post('/', [FormController::class, 'store'])->name('store');
            Route::get('/{form}', [FormController::class, 'show'])->name('show');
            Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit');
            Route::put('/{form}', [FormController::class, 'update'])->name('update');
            Route::delete('/{form}', [FormController::class, 'destroy'])->name('destroy');

            // Form actions
            Route::post('/{form}/clone', [FormController::class, 'clone'])->name('clone');
            Route::post('/{form}/activate', [FormController::class, 'activate'])->name('activate');
            Route::post('/{form}/archive', [FormController::class, 'archive'])->name('archive');
            Route::post('/{form}/restore', [FormController::class, 'restore'])->name('restore');

            // Version management
            Route::post('/{form}/versions/{version}/publish', [FormController::class, 'publish'])->name('version.publish');

            // Target management
            Route::post('/{form}/targets', [FormController::class, 'createTarget'])->name('targets.store');
        });

        // Query ticket management
        Route::prefix('queries')->name('queries.')->middleware('can:review_form')->group(function () {
            Route::get('/list', [QueryTicketController::class, 'index'])->name('index');
            Route::get('/{ticket}', [QueryTicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/replies', [QueryTicketController::class, 'storeReply'])->name('replies.store');
            Route::post('/{ticket}/status', [QueryTicketController::class, 'updateStatus'])->name('status.update');
        });
    });
});
