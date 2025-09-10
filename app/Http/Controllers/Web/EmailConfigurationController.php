<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
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
    public function templates(Request $request): Response
    {
        $query = EmailTemplate::query()
            ->select([
                'id',
                'name',
                'type',
                'subject',
                'is_active',
                'version',
                'description',
                'variables',
                'created_at',
                'updated_at'
            ]);

        // Apply filters
        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $isActive = $request->input('is_active') === 'active';
            $query->where('is_active', $isActive);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Order by updated_at desc to show latest first
        $query->orderBy('updated_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $templates = $query->paginate($perPage);

        // Transform the data to include variables_count
        $templates->getCollection()->transform(function ($template) {
            $template->variables_count = is_array($template->variables) ? count($template->variables) : 0;
            return $template;
        });

        return Inertia::render('Admin/EmailTemplate/Index', [
            'templates' => $templates,
            'templateTypes' => EmailTemplate::getTypes(),
            'filters' => $request->only(['type', 'is_active', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new email template.
     */
    public function createTemplate(Request $request): Response
    {
        $originalTemplate = null;
        $isCreatingVersion = false;

        // Check if this is for creating a new version
        if ($request->has('template_id') && $request->boolean('create_version')) {
            $templateId = $request->integer('template_id');
            $originalTemplate = EmailTemplate::find($templateId);

            if ($originalTemplate) {
                $isCreatingVersion = true;
            }
        }

        return Inertia::render('Admin/EmailTemplate/Create', [
            'templateTypes' => EmailTemplate::getTypes(),
            'originalTemplate' => $originalTemplate,
            'isCreatingVersion' => $isCreatingVersion,
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
     * Preview an email template.
     */
    public function previewTemplate(EmailTemplate $template)
    {
        // Load the full template with all content
        $fullTemplate = EmailTemplate::select([
            'id',
            'name',
            'type',
            'subject',
            'html_content',
            'text_content',
            'variables',
            'is_active',
            'version',
            'description'
        ])->find($template->id);

        if (!$fullTemplate) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        // Generate preview data for the template
        $previewData = [
            'subject' => $fullTemplate->subject,
            'html_content' => $fullTemplate->html_content,
            'text_content' => $fullTemplate->text_content,
            'variables' => $fullTemplate->variables ?? [],
        ];

        return response()->json([
            'success' => true,
            'previewData' => $previewData,
        ]);
    }

    /**
     * Delete an email template.
     */
    public function deleteTemplate(EmailTemplate $template)
    {
        $template->delete();

        return redirect()->back()->with('success', 'Template deleted successfully.');
    }

    /**
     * Display the bulk email composer page.
     */
    public function bulkEmail(): Response
    {
        return Inertia::render('Admin/BulkEmail/Index');
    }
}
