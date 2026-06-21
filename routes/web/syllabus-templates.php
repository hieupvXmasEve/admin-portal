<?php

use App\Http\Controllers\Web\SyllabusTemplateController;
use Illuminate\Support\Facades\Route;

// Inertia pages for managing Syllabus Templates
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/syllabus-templates', [SyllabusTemplateController::class, 'pageIndex'])
        ->middleware('can:view_syllabus')
        ->name('syllabus_templates.index');

    Route::get('/syllabus-templates/create', [SyllabusTemplateController::class, 'pageCreate'])
        ->middleware('can:create_syllabus')
        ->name('syllabus_templates.create');

    // Allow direct create with unit_id in body (for API-like usage)
    Route::post('/syllabus-templates', [SyllabusTemplateController::class, 'store'])
        ->middleware('can:create_syllabus')
        ->name('syllabus_templates.store.direct');

    Route::get('/syllabus-templates/{syllabusTemplate}/edit', [SyllabusTemplateController::class, 'pageEdit'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.edit');

    Route::get('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'pageShow'])
        ->middleware('can:view_syllabus')
        ->name('syllabus_templates.show');

    // Non-API actions via Inertia (redirect responses)
    Route::post('syllabus-templates', [SyllabusTemplateController::class, 'store'])
        ->middleware('can:create_syllabus')
        ->name('syllabus_templates.store');

    Route::put('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'update'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.update');

    Route::delete('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'destroy'])
        ->middleware('can:delete_syllabus')
        ->name('syllabus_templates.destroy');

    Route::patch('/syllabus-templates/{syllabusTemplate}/toggle-active', [SyllabusTemplateController::class, 'toggleActive'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.toggle-active');

    Route::post('/syllabus-templates/{syllabusTemplate}/set-default', [SyllabusTemplateController::class, 'setDefault'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.set-default');

    Route::post('/syllabus-templates/{syllabusTemplate}/clone', [SyllabusTemplateController::class, 'clone'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.clone');
});

// API endpoints for Syllabus Templates (authenticated SPA) reusing the same web controller
Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {
    Route::get('/units/{unit}/syllabus-templates', [SyllabusTemplateController::class, 'index'])
        ->middleware('can:view_syllabus')
        ->name('syllabus_templates.index');

    Route::post('/units/{unit}/syllabus-templates', [SyllabusTemplateController::class, 'store'])
        ->middleware('can:create_syllabus')
        ->name('syllabus_templates.store');

    // Grading scheme admin endpoints (S-003) — declared before the wildcard show route.
    Route::get('/syllabus-templates/grading-scheme/options', [SyllabusTemplateController::class, 'gradingSchemeOptions'])
        ->middleware('can:view_syllabus')
        ->name('syllabus_templates.grading-scheme.options');

    Route::post('/syllabus-templates/grading-scheme/preview', [SyllabusTemplateController::class, 'previewGradingScheme'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.grading-scheme.preview');

    Route::post('/syllabus-templates/{syllabusTemplate}/grading-scheme/preview', [SyllabusTemplateController::class, 'previewExistingGradingScheme'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.grading-scheme.preview-existing');

    Route::get('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'show'])
        ->middleware('can:view_syllabus')
        ->name('syllabus_templates.show');

    Route::put('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'update'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.update');

    Route::delete('/syllabus-templates/{syllabusTemplate}', [SyllabusTemplateController::class, 'destroy'])
        ->middleware('can:delete_syllabus')
        ->name('syllabus_templates.destroy');

    Route::patch('/syllabus-templates/{syllabusTemplate}/toggle-active', [SyllabusTemplateController::class, 'toggleActive'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.toggle-active');

    Route::post('/syllabus-templates/{syllabusTemplate}/set-default', [SyllabusTemplateController::class, 'setDefault'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.set-default');

    Route::post('/syllabus-templates/{syllabusTemplate}/clone', [SyllabusTemplateController::class, 'clone'])
        ->middleware('can:edit_syllabus')
        ->name('syllabus_templates.clone');
});
