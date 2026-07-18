<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Institution\Queries\ListDepartmentsQuery;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly ListDepartmentsQuery $departments,
    ) {
        $this->middleware('can:manage_departments');
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Departments/Index', [
            'departments' => $this->departments->handle(),
        ]);
    }
}
