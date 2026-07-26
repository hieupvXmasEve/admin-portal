<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Delivery\Queries\ListAttendanceRecordsQuery;
use App\Modules\Academic\Http\Requests\Delivery\ListAttendanceRecordsRequest;
use Inertia\Inertia;
use Inertia\Response;

final class AttendanceController extends Controller
{
    public function index(ListAttendanceRecordsRequest $request, ListAttendanceRecordsQuery $query): Response
    {
        return Inertia::render('Attendance/Index', $query->handle($request->validated()));
    }
}
