<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
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
        return Inertia::render('Admin/EmailTemplate/Index', [
            'templateTypes' => EmailTemplate::getTypes(),
        ]);
    }

    /**
     * Show the form for creating a new email template.
     */
    public function createTemplate(): Response
    {
        return Inertia::render('Admin/EmailTemplate/Create', [
            'templateTypes' => EmailTemplate::getTypes(),
        ]);
    }

    /**
     * Show the form for editing an email template.
     */
    public function editTemplate(EmailTemplate $template): Response
    {
        return Inertia::render('Admin/EmailTemplate/Edit', [
            'template' => $template,
            'templateTypes' => EmailTemplate::getTypes(),
        ]);
    }

    /**
     * Display the bulk email composer page.
     */
    public function bulkEmail(): Response
    {
        return Inertia::render('Admin/BulkEmail/Index');
    }
}
