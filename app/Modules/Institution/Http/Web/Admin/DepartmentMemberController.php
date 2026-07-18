<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Institution\Actions\AddDepartmentMemberAction;
use App\Modules\Institution\Actions\RemoveDepartmentMemberAction;
use App\Modules\Institution\Actions\UpdateDepartmentMemberAction;
use App\Modules\Institution\Http\Requests\Department\StoreDepartmentMemberRequest;
use App\Modules\Institution\Http\Requests\Department\UpdateDepartmentMemberRequest;
use App\Modules\Institution\Queries\GetDepartmentMembershipAdministrationQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentMemberController extends Controller
{
    public function __construct(
        private readonly GetDepartmentMembershipAdministrationQuery $administration,
    ) {
        $this->middleware('can:manage_departments');
    }

    public function index(int|string $department): Response
    {
        return Inertia::render('Admin/Departments/Members', $this->administration->handle($department));
    }

    public function store(StoreDepartmentMemberRequest $request, int|string $department): RedirectResponse
    {
        AddDepartmentMemberAction::run([
            ...$request->validated(),
            'department_id' => (int) $department,
        ]);

        Inertia::flash('success', 'Member added successfully.');

        return back();
    }

    public function update(
        UpdateDepartmentMemberRequest $request,
        int|string $department,
        int|string $member,
    ): RedirectResponse {
        UpdateDepartmentMemberAction::run([
            ...$request->validated(),
            'department_id' => (int) $department,
            'membership_id' => (int) $member,
        ]);

        Inertia::flash('success', 'Member updated successfully.');

        return back();
    }

    public function destroy(int|string $department, int|string $member): RedirectResponse
    {
        RemoveDepartmentMemberAction::run([
            'department_id' => (int) $department,
            'membership_id' => (int) $member,
        ]);

        Inertia::flash('success', 'Member removed successfully.');

        return back();
    }
}
