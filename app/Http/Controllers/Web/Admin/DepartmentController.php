<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage_departments');
    }

    public function index()
    {
        $departments = Department::withCount('memberships')->get();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }
}
