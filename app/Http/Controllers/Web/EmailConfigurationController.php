<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigurationController extends Controller
{
    /**
     * Display the email configuration management page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/EmailConfiguration/Index');
    }

    /**
     * Display the email template management page.
     */
    public function templates(): Response
    {
        return Inertia::render('Admin/EmailTemplate/Index');
    }

    /**
     * Display the bulk email composer page.
     */
    public function bulkEmail(): Response
    {
        return Inertia::render('Admin/BulkEmail/Index');
    }
}
