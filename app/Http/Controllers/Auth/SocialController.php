<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SocialController extends Controller
{
    public function redirect(Request $request)
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {

            $user_social = Socialite::driver('google')->user();

            $user = User::where('email', $user_social->getEmail())->first();

            if ($user) {
                Auth::login($user);
                $request->session()->put('email', $user->email);

                return redirect()->route('dashboard');
            } else {
                return redirect()->route('home');
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
