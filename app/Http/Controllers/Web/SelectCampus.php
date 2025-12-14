<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class SelectCampus extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $campuses = $user->campuses->unique('id');

        return Inertia::render('SelectCampus', [
            'campuses' => $campuses,
        ]);
    }

    public function setCurrentCampus(Request $request)
    {

        try {
            $validated = $request->validate([
                'selectedCampus' => 'required|exists:campuses,id',
            ]);

            $campusId = $validated['selectedCampus'];
            // Lưu campus vào session
            Session::put('current_campus_id', $campusId);

            /** @var \App\Models\User $user */
            // Lấy tất cả permission code từ User model
            $user = Auth::user();
            $permissions = $user->getAllPermissions($campusId);

            // Lưu permissions vào session
            Session::put('permissions', $permissions);
            Session::save();

            return redirect()->route('dashboard');
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
