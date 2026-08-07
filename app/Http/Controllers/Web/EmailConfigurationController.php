<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigurationController extends Controller
{
    /**
     * Display the email configuration management page.
     */
    public function index(): Response
    {
        $campuses = Campus::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Admin/EmailConfiguration/Index', [
            'campuses' => $campuses,
            'currentCampusId' => session('current_campus_id') ? (int) session('current_campus_id') : null,
        ]);
    }
}
