<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Support\Str;

class SocialController extends Controller
{
    public function redirect(Request $request)
    {
        return Socialite::driver('google')->with(['hd' => 'fpt.edu.vn'])->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $user_social = Socialite::driver('google')->user();
            $user = User::where('email', $user_social->getEmail())->first();
            // Check if user is from fp.edu.vn
            $domain = Str::after($user_social->getEmail(), '@');
            if ($domain !== 'fpt.edu.vn') {
                return redirect()->route('login', [
                    'error' => 'Email is not from @fpt.edu.vn',
                    'email' => $user_social->email,
                ]);
            }
            if ($user) {
                Auth::login($user);
                $request->session()->put('email', $user->email);

                return redirect()->route('select-campus.index');
            } else {
                return redirect()->route('login', [
                    'error' => 'Email is not registered',
                    'email' => $user_social->email,
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
