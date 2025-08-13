<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailTemplateService
{
    /**
     * Create a new email template
     */
    public function createTemplate(array $data): EmailTemplate
    {
        // Validate the data
        $this->validateTemplateData($data);

        // Extract variables from content
        $template = new EmailTemplate($data);
        $variables = $template->extractVariables();
        $data['variables'] = $variables;

        // Create the template
        $template = EmailTemplate::create($data);

        Log::info('Email template created', [
            'template_id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'version' => $template->version,
        ]);

        return $template;
    }

    /**
     * Update an existing email template
     */
    public function updateTemplate(EmailTemplate $template, array $data): EmailTemplate
    {
        // Validate the data
        $this->validateTemplateData($data, $template->id);

        // Extract variables from content
        $tempTemplate = new EmailTemplate($data);
        $variables = $tempTemplate->extractVariables();
        $data['variables'] = $variables;

        // Update the template
        $template->update($data);

        Log::info('Email template updated', [
            'template_id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'version' => $template->version,
        ]);

        return $template->fresh();
    }

    /**
     * Create a new version of an existing template
     */
    public function createNewVersion(EmailTemplate $template, array $data): EmailTemplate
    {
        // Validate the data
        $this->validateTemplateData($data);

        // Extract variables from content
        $tempTemplate = new EmailTemplate($data);
        $variables = $tempTemplate->extractVariables();
        $data['variables'] = $variables;

        // Create new version
        $newTemplate = $template->createNewVersion($data);

        Log::info('Email template new version created', [
            'template_id' => $newTemplate->id,
            'parent_id' => $template->id,
            'name' => $newTemplate->name,
            'version' => $newTemplate->version,
        ]);

        return $newTemplate;
    }

    /**
     * Delete an email template
     */
    public function deleteTemplate(EmailTemplate $template): bool
    {
        $templateId = $template->id;
        $templateName = $template->name;
        $templateVersion = $template->version;
        
        $deleted = $template->delete();

        if ($deleted) {
            Log::info('Email template deleted', [
                'template_id' => $templateId,
                'name' => $templateName,
                'version' => $templateVersion,
            ]);
        }

        return $deleted;
    }

    /**
     * Render template with variables
     */
    public function renderTemplate(EmailTemplate $template, array $variables = []): array
    {
        // Validate required variables
        $missingVariables = $template->validateVariables($variables);
        if (!empty($missingVariables)) {
            throw new \InvalidArgumentException(
                'Missing required template variables: ' . implode(', ', $missingVariables)
            );
        }

        return $template->render($variables);
    }

    /**
     * Get templates by type
     */
    public function getTemplatesByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return EmailTemplate::getByType($type);
    }

    /**
     * Get latest version of a template by name
     */
    public function getLatestTemplate(string $name): ?EmailTemplate
    {
        return EmailTemplate::getLatestVersion($name);
    }

    /**
     * Get all templates with pagination
     */
    public function getAllTemplates(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = EmailTemplate::query();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('subject', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('name')
            ->orderBy('version', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Validate template content
     */
    public function validateTemplate(string $content): array
    {
        $errors = [];

        // Check for basic HTML structure if it contains HTML
        if (strpos($content, '<') !== false) {
            // Basic HTML validation
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            
            if (!$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                $errors[] = 'Invalid HTML structure';
            }
            
            libxml_clear_errors();
        }

        // Check for unclosed template variables
        if (preg_match('/\{\{[^}]*$/', $content)) {
            $errors[] = 'Unclosed template variable found';
        }

        if (preg_match('/^[^{]*\}\}/', $content)) {
            $errors[] = 'Unopened template variable found';
        }

        return $errors;
    }

    /**
     * Preview template with sample data
     */
    public function previewTemplate(EmailTemplate $template, array $sampleData = []): array
    {
        // Use default sample data if none provided
        if (empty($sampleData)) {
            $sampleData = $this->getDefaultSampleData($template->type);
        }

        try {
            return $this->renderTemplate($template, $sampleData);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'subject' => $template->subject,
                'html' => $template->html_content,
                'text' => $template->text_content,
            ];
        }
    }

    /**
     * Get default sample data for template types
     */
    protected function getDefaultSampleData(string $type): array
    {
        return match ($type) {
            EmailTemplate::TYPE_WELCOME => [
                'user_name' => 'John Doe',
                'course_name' => 'Computer Science',
                'semester' => 'Fall 2024',
                'login_url' => 'https://example.com/login',
            ],
            EmailTemplate::TYPE_GRADE_NOTIFICATION => [
                'student_name' => 'Jane Smith',
                'course_name' => 'Mathematics 101',
                'assessment_name' => 'Midterm Exam',
                'grade' => 'A',
                'total_points' => '95/100',
            ],
            EmailTemplate::TYPE_COURSE_REGISTRATION => [
                'student_name' => 'Bob Johnson',
                'course_name' => 'Physics 201',
                'course_code' => 'PHYS201',
                'semester' => 'Spring 2024',
                'registration_date' => now()->format('Y-m-d'),
            ],
            EmailTemplate::TYPE_ACADEMIC_HOLD => [
                'student_name' => 'Alice Brown',
                'hold_type' => 'Financial Hold',
                'hold_reason' => 'Outstanding tuition balance',
                'contact_info' => 'finance@university.edu',
            ],
            default => [
                'user_name' => 'Sample User',
                'date' => now()->format('Y-m-d'),
                'time' => now()->format('H:i'),
                'system_name' => config('app.name'),
            ],
        };
    }

    /**
     * Validate template data
     */
    protected function validateTemplateData(array $data, ?int $excludeId = null): void
    {
        $rules = EmailTemplate::validationRules();
        
        // Add unique name+version validation
        $nameRule = 'required|string|max:255';
        if (isset($data['version'])) {
            $nameRule .= '|unique:email_templates,name,NULL,id,version,' . $data['version'];
            if ($excludeId) {
                $nameRule .= ',id,' . $excludeId;
            }
        }
        $rules['name'] = $nameRule;

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional content validation
        $contentErrors = $this->validateTemplate($data['html_content']);
        if (!empty($contentErrors)) {
            throw new \InvalidArgumentException('Template validation failed: ' . implode(', ', $contentErrors));
        }
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        $total = EmailTemplate::count();
        $active = EmailTemplate::where('is_active', true)->count();
        $byType = EmailTemplate::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_type' => $byType,
        ];
    }
}
