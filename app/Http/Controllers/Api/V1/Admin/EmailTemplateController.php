<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateService $templateService
    ) {
    }

    /**
     * Display a listing of email templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'search' => 'nullable|string',
                'per_page' => 'nullable|integer|min:10|max:100',
            ]);

            $templates = $this->templateService->getAllTemplates($validated);
            $statistics = $this->templateService->getTemplateStatistics();

            return response()->json([
                'success' => true,
                'data' => $templates,
                'statistics' => $statistics,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve email templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created email template.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(EmailTemplate::validationRules());
            
            $template = $this->templateService->createTemplate($validated);

            return response()->json([
                'success' => true,
                'message' => 'Email template created successfully',
                'data' => $template,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified email template.
     */
    public function show(EmailTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $template->load(['parent', 'children']),
        ]);
    }

    /**
     * Update the specified email template.
     */
    public function update(Request $request, EmailTemplate $template): JsonResponse
    {
        try {
            $rules = EmailTemplate::validationRules();
            // Make name optional for updates
            $rules['name'] = 'nullable|string|max:255';
            
            $validated = $request->validate($rules);
            
            $template = $this->templateService->updateTemplate($template, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Email template updated successfully',
                'data' => $template,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified email template.
     */
    public function destroy(EmailTemplate $template): JsonResponse
    {
        try {
            $this->templateService->deleteTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'Email template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new version of template.
     */
    public function createVersion(Request $request, EmailTemplate $template): JsonResponse
    {
        try {
            $validated = $request->validate(EmailTemplate::validationRules());
            
            $newTemplate = $this->templateService->createNewVersion($template, $validated);

            return response()->json([
                'success' => true,
                'message' => 'New template version created successfully',
                'data' => $newTemplate,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template version',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, EmailTemplate $template): JsonResponse
    {
        try {
            $validated = $request->validate([
                'variables' => 'nullable|array',
            ]);

            $preview = $this->templateService->previewTemplate(
                $template,
                $validated['variables'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate template content.
     */
    public function validateTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
            ]);

            $errors = $this->templateService->validateTemplate($validated['content']);

            return response()->json([
                'success' => empty($errors),
                'errors' => $errors,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get templates by type.
     */
    public function getByType(string $type): JsonResponse
    {
        try {
            $templates = $this->templateService->getTemplatesByType($type);

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates by type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available template types.
     */
    public function getTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EmailTemplate::getTypes(),
        ]);
    }
}
