<?php

namespace App\Modules\Identity\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\SetCurrentCampusAction;
use App\Modules\Identity\Http\Requests\Identity\SelectCampusRequest;
use App\Modules\Identity\IdentityContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class CampusSelectionController extends Controller
{
    public function index(IdentityContext $identity)
    {
        $user = $identity->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $campuses = $user->campuses->unique('id');

        return Inertia::render('SelectCampus', [
            'campuses' => $campuses,
        ]);
    }

    public function setCurrentCampus(SelectCampusRequest $request, IdentityContext $identity)
    {
        try {
            SetCurrentCampusAction::run(
                $identity->user(),
                $request->validated('selectedCampus')
            );

            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            Log::error('Error in setCurrentCampus:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);

            return back()->withErrors(['error' => 'Failed to set campus']);
        }
    }

    public function changeCampus()
    {
        try {
            // Clear campus-related session data
            Session::forget(['current_campus_id', 'permissions']);
            Session::save();

            return redirect()->route('select-campus.index');
        } catch (\Exception $e) {
            Log::error('Error in changeCampus:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);

            return back()->withErrors(['error' => 'Failed to change campus']);
        }
    }
}
