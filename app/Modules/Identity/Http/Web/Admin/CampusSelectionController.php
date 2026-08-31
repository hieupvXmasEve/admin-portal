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

        $campuses = $user->campuses->unique('id')->values();

        return Inertia::render('SelectCampus', [
            'campuses' => $campuses,
        ]);
    }

    public function setCurrentCampus(SelectCampusRequest $request, IdentityContext $identity)
    {
        try {
            $wasSwitching = Session::has('current_campus_id');

            SetCurrentCampusAction::run(
                $identity->user(),
                $request->validated('selectedCampus')
            );

            // Switching campus from the sidebar keeps the current page so the
            // user does not lose context; initial selection goes to dashboard.
            return $wasSwitching ? back() : redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            Log::error('Error in setCurrentCampus:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);

            return back()->withErrors(['error' => 'Failed to set campus']);
        }
    }
}
