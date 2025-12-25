<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class DepartmentMemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage_departments');
    }

    public function index(Department $department)
    {
        $members = $department->memberships()
            ->with('user')
            ->get();

        return Inertia::render('Admin/Departments/Members', [
            'department' => $department,
            'members' => $members,
            'availableUsers' => User::whereNotIn('id', $members->pluck('user_id'))->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request, Department $department)
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('department_memberships')->where('department_id', $department->id),
            ],
            'department_role' => 'required|in:head,staff',
        ]);

        $department->memberships()->create($validated);

        return back()->with('success', 'Member added successfully.');
    }

    public function update(Request $request, Department $department, DepartmentMembership $member)
    {
        $validated = $request->validate([
            'department_role' => 'required|in:head,staff',
            'is_active' => 'required|boolean',
        ]);

        $member->update($validated);

        return back()->with('success', 'Member updated successfully.');
    }

    public function destroy(Department $department, DepartmentMembership $member)
    {
        $member->delete();

        return back()->with('success', 'Member removed successfully.');
    }
}
