<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    protected $assignmentService;

    public function __construct(RoleAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Assign a role to a user at a specific campus
     */
    public function store(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'campus_id' => 'required|exists:campuses,id',
        ]);

        $role = Role::findOrFail($request->input('role_id'));
        $this->assignmentService->assignRoleToUser($user, $role, $request->input('campus_id'));

        return back()->with('success', 'Role assigned successfully.');
    }

    /**
     * Remove a role from a user at a specific campus
     */
    public function destroy(Request $request, User $user, Role $role)
    {
        $request->validate([
            'campus_id' => 'required|exists:campuses,id',
        ]);

        $this->assignmentService->removeRoleFromUser($user, $role, $request->input('campus_id'));

        return back()->with('success', 'Role removed successfully.');
    }
}
