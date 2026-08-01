<?php

declare(strict_types=1);

use App\Modules\Merchandise\Http\Api\MerchandiseController;
use App\Modules\Merchandise\Http\Api\MerchandiseImageController;
use App\Modules\Merchandise\Http\Api\MerchandiseVariantController;
use App\Modules\Merchandise\Http\Web\MerchandiseWebController;
use Illuminate\Support\Facades\Route;

/**
 * GET page routes render Inertia (Http/Web) — the admin list/create/edit/show
 * screens. Write routes stay JSON API (Http/Api, ApiResponse envelope); Vue
 * pages call them via fetch() + router.reload(). All under 'web' + 'auth'
 * (session guard + CSRF), same shape as routes/api/admin.php.
 */
Route::middleware(['web', 'auth'])->prefix('merchandise')->name('merchandise.')->group(function () {
    Route::get('/', [MerchandiseWebController::class, 'index'])
        ->middleware('can:view_merchandise')
        ->name('index');

    // Static segment before the {merchandise} wildcard so it isn't captured
    // as a route-model-bound id.
    Route::get('/create', [MerchandiseWebController::class, 'create'])
        ->middleware('can:create_merchandise')
        ->name('create');

    Route::get('/{merchandise}/edit', [MerchandiseWebController::class, 'edit'])
        ->middleware('can:edit_merchandise')
        ->name('edit');

    Route::get('/{merchandise}', [MerchandiseWebController::class, 'show'])
        ->middleware('can:view_merchandise')
        ->name('show');

    Route::post('/', [MerchandiseController::class, 'store'])
        ->middleware('can:create_merchandise')
        ->name('store');

    Route::put('/{merchandise}', [MerchandiseController::class, 'update'])
        ->middleware('can:edit_merchandise')
        ->name('update');

    Route::post('/{merchandise}/archive', [MerchandiseController::class, 'archive'])
        ->middleware('can:archive_merchandise')
        ->name('archive');

    Route::post('/{merchandise}/images', [MerchandiseImageController::class, 'store'])
        ->middleware('can:manage_merchandise_image')
        ->name('images.store');

    Route::post('/{merchandise}/images/reorder', [MerchandiseImageController::class, 'reorder'])
        ->middleware('can:manage_merchandise_image')
        ->name('images.reorder');

    Route::post('/images/{merchandiseImage}/primary', [MerchandiseImageController::class, 'setPrimary'])
        ->middleware('can:manage_merchandise_image')
        ->name('images.set-primary');

    Route::delete('/images/{merchandiseImage}', [MerchandiseImageController::class, 'destroy'])
        ->middleware('can:manage_merchandise_image')
        ->name('images.destroy');

    Route::post('/{merchandise}/variants', [MerchandiseVariantController::class, 'store'])
        ->middleware('can:manage_merchandise_variant')
        ->name('variants.store');

    Route::put('/variants/{variant}', [MerchandiseVariantController::class, 'update'])
        ->middleware('can:manage_merchandise_variant')
        ->name('variants.update');

    Route::post('/variants/{variant}/stock-adjust', [MerchandiseVariantController::class, 'adjustStock'])
        ->middleware('can:adjust_merchandise_stock')
        ->name('variants.stock-adjust');

    Route::get('/variants/{variant}/stock-movements', [MerchandiseVariantController::class, 'stockMovements'])
        ->middleware('can:view_merchandise_audit')
        ->name('variants.stock-movements');
});
