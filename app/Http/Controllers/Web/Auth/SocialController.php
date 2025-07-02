<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
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
                return redirect()->route('select-campus.index');
            } else {
                return redirect()->route('login', [
                    'error' => 'Email is not registered',
                    'email' => $user_social->email
                ]);
            }
        } catch (InvalidStateException $e) {
            // Handle invalid state exception - redirect back to login
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        } catch (\Exception $e) {
            // Handle other exceptions
            return redirect()->route('login')->with('error', 'Authentication error: ' . $e->getMessage());
        }
    }
}
