<?php

namespace App\Modules\Identity\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\LoginAction;
use App\Modules\Identity\Actions\LogoutAction;
use App\Modules\Identity\Http\Requests\Identity\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'error' => $request->error,
            'email' => $request->email,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        LoginAction::run($request);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        LogoutAction::run($request);

        return redirect()->route('login');
    }
}
