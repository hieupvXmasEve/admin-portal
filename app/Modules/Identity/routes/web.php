<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Web\Admin\CampusSelectionController;
use App\Modules\Identity\Http\Web\Admin\LoginController;
use App\Modules\Identity\Http\Web\Admin\RoleController;
use App\Modules\Identity\Http\Web\Admin\SocialAuthController;
use App\Modules\Identity\Http\Web\Admin\UserController;
use App\Modules\Identity\Http\Web\Auth\ConfirmablePasswordController;
use App\Modules\Identity\Http\Web\Auth\EmailVerificationNotificationController;
use App\Modules\Identity\Http\Web\Auth\EmailVerificationPromptController;
use App\Modules\Identity\Http\Web\Auth\NewPasswordController;
use App\Modules\Identity\Http\Web\Auth\PasswordResetLinkController;
use App\Modules\Identity\Http\Web\Auth\VerifyEmailController;
use App\Modules\Identity\Http\Web\Settings\PasswordController;
use App\Modules\Identity\Http\Web\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('web')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('auth/google/redirect', [SocialAuthController::class, 'redirect'])->name('google.login');
        Route::get('auth/google/callback', [SocialAuthController::class, 'callback']);

        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store']);

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });

    // Auth routes
    Route::middleware('auth')->group(function () {
        Route::get('select-campus', [CampusSelectionController::class, 'index'])->name('select-campus.index');
        Route::post('select-campus/set-current', [CampusSelectionController::class, 'setCurrentCampus'])->name('select-campus.set-current');
        Route::post('select-campus/change', [CampusSelectionController::class, 'changeCampus'])->name('select-campus.change');

        // User management routes
        Route::middleware('can:view_user')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('identity.users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('identity.users.create')->middleware('can:create_user');
            Route::post('users', [UserController::class, 'store'])->name('identity.users.store')->middleware('can:create_user');
            Route::get('users/{user}', [UserController::class, 'show'])->name('identity.users.show');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('identity.users.edit')->middleware('can:edit_user');
            Route::put('users/{user}', [UserController::class, 'update'])->name('identity.users.update')->middleware('can:edit_user');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('identity.users.destroy')->middleware('can:delete_user');
        });

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:view_role');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('can:create_role');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:create_role');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show')->middleware('can:view_role');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('can:edit_role');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('can:edit_role');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:delete_role');

        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

        Route::redirect('settings', '/settings/profile');
        Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');
        Route::get('settings/appearance', fn () => Inertia::render('Settings/Appearance'))->name('appearance');
    });
});
