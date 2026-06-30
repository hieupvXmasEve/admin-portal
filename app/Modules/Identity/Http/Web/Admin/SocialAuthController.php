<?php

namespace App\Modules\Identity\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\AuthenticateSocialUserAction;
use App\Shared\DTO\SocialUserData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->with(['hd' => 'fpt.edu.vn'])->redirect();
        // return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $socialUser = Socialite::driver('google')->user();

            // Check domain
            $domain = Str::after($socialUser->getEmail(), '@');
            if ($domain !== 'fpt.edu.vn') {
                return redirect()->route('login', [
                    'error' => 'Email is not from @fpt.edu.vn',
                    'email' => $socialUser->getEmail(),
                ]);
            }

            $user = AuthenticateSocialUserAction::run(new SocialUserData(
                email: $socialUser->getEmail(),
                name: $socialUser->getName() ?? 'User',
            ));

            $request->session()->put('email', $user->email);

            // Honor an intended URL (e.g. the Passport /oauth/authorize consent for the MCP
            // server) just like password login does; fall back to campus selection otherwise.
            return redirect()->intended(route('select-campus.index'));
        } catch (InvalidStateException $e) {
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        } catch (\Exception $e) {
            return redirect()->route('login', [
                'error' => $e->getMessage(),
                'email' => isset($socialUser) ? $socialUser->getEmail() : null,
            ]);
        }
    }
}
