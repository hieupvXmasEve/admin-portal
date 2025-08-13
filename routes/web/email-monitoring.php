<?php

use App\Http\Controllers\Admin\EmailMonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('email-monitoring')->name('email-monitoring.')->group(function () {
        // Dashboard
        Route::get('/', [EmailMonitoringController::class, 'index'])
            ->middleware('can:view_email_system')
            ->name('dashboard');

        // API endpoints for dashboard data
        Route::get('/statistics', [EmailMonitoringController::class, 'getStatistics'])
            ->middleware('can:view_email_system')
            ->name('statistics');

        Route::get('/queue-status', [EmailMonitoringController::class, 'getQueueStatus'])
            ->middleware('can:view_email_system')
            ->name('queue-status');

        Route::get('/logs', [EmailMonitoringController::class, 'getLogs'])
            ->middleware('can:view_email_system')
            ->name('logs');

        Route::get('/delivery-trends', [EmailMonitoringController::class, 'getDeliveryTrends'])
            ->middleware('can:view_email_system')
            ->name('delivery-trends');

        Route::get('/failure-analysis', [EmailMonitoringController::class, 'getFailureAnalysis'])
            ->middleware('can:view_email_system')
            ->name('failure-analysis');

        // Batch management
        Route::get('/batches/{batchId}/progress', [EmailMonitoringController::class, 'getBatchProgress'])
            ->middleware('can:view_email_system')
            ->name('batch-progress');

        Route::post('/batches/{batchId}/cancel', [EmailMonitoringController::class, 'cancelBatch'])
            ->middleware('can:manage_email_system')
            ->name('cancel-batch');

        // System health
        Route::post('/test-health', [EmailMonitoringController::class, 'testSystemHealth'])
            ->middleware('can:manage_email_system')
            ->name('test-health');

        // Export
        Route::get('/export', [EmailMonitoringController::class, 'exportStatistics'])
            ->middleware('can:view_email_system')
            ->name('export');
    });
});
