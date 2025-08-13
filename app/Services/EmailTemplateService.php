<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmailTemplateService
{
    /**
     * Get paginated email templates with optional filtering
     */
    public function getPaginatedTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = EmailTemplate::query();

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('subject', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('name')
                    ->orderBy('version', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Get all active templates by type
     */
    public function getTemplatesByType(string $type): Collection
    {
        return EmailTemplate::getByType($type);
    }

    /**
     * Get all available template types
     */
    public function getTemplateTypes(): array
    {
        return EmailTemplate::getTypes();
    }

    /**
     * Create a new email template
     */
    public function createTemplate(array $data): EmailTemplate
    {
        $this->validateTemplateData($data);
        $this->validateTemplateContent($data);

        return DB::transaction(function () use ($data) {
            // Set initial version if not provided
            if (!isset($data['version'])) {
                $data['version'] = 1;
            }

            // Set active by default if not provided
            if (!isset($data['is_active'])) {
                $data['is_active'] = true;
            }

            // Extract variables from content
            $template = new EmailTemplate($data);
            $data['variables'] = $template->extractVariables();

            return EmailTemplate::create($data);
        });
    }

    /**
     * Update an existing email template
     */
    public function updateTemplate(int $id, array $data): EmailTemplate
    {
        $template = $this->findTemplate($id);
        $this->validateTemplateData($data, $id);
        $this->validateTemplateContent($data);

        return DB::transaction(function () use ($template, $data) {
            // Extract variables from updated content
            $tempTemplate = new EmailTemplate(array_merge($template->toArray(), $data));
            $data['variables'] = $tempTemplate->extractVariables();

            $template->update($data);
            return $template->fresh();
        });
    }

    /**
     * Create a new version of an existing template
     */
    public function createNewVersion(int $templateId, array $data): EmailTemplate
    {
        $originalTemplate = $this->findTemplate($templateId);

        // Don't validate template data for new versions as name uniqueness is handled differently
        $this->validateTemplateContent($data);

        return DB::transaction(function () use ($originalTemplate, $data) {
            // Deactivate previous versions
            EmailTemplate::where('name', $originalTemplate->name)
                        ->update(['is_active' => false]);

            // Extract variables from updated content
            $tempTemplate = new EmailTemplate(array_merge($originalTemplate->toArray(), $data));
            $data['variables'] = $tempTemplate->extractVariables();

            // Create new version
            return $originalTemplate->createNewVersion(array_merge($data, [
                'is_active' => true,
            ]));
        });
    }

    /**
     * Delete an email template
     */
    public function deleteTemplate(int $id): bool
    {
        $template = $this->findTemplate($id);

        return DB::transaction(function () use ($template) {
            // If this is the active version, activate the previous version
            if ($template->is_active && $template->parent_id) {
                $template->parent->update(['is_active' => true]);
            }

            return $template->delete();
        });
    }

    /**
     * Find template by ID
     */
    public function findTemplate(int $id): EmailTemplate
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            throw new \InvalidArgumentException("Email template with ID {$id} not found.");
        }

        return $template;
    }

    /**
     * Get template by name (latest active version)
     */
    public function getTemplateByName(string $name): ?EmailTemplate
    {
        return EmailTemplate::getLatestVersion($name);
    }

    /**
     * Render template with provided variables
     */
    public function renderTemplate(int $templateId, array $variables = []): array
    {
        $template = $this->findTemplate($templateId);

        // Validate that all required variables are provided
        $missingVariables = $template->validateVariables($variables);
        if (!empty($missingVariables)) {
            throw new ValidationException(
                validator([], []),
                ['variables' => 'Missing required variables: ' . implode(', ', $missingVariables)]
            );
        }

        return $template->render($variables);
    }

    /**
     * Render template by name with provided variables
     */
    public function renderTemplateByName(string $name, array $variables = []): array
    {
        $template = $this->getTemplateByName($name);

        if (!$template) {
            throw new \InvalidArgumentException("No active template found with name: {$name}");
        }

        return $this->renderTemplate($template->id, $variables);
    }

    /**
     * Preview template rendering with sample data
     */
    public function previewTemplate(int $templateId, array $sampleVariables = []): array
    {
        $template = $this->findTemplate($templateId);

        // Get required variables and provide default values for missing ones
        $requiredVariables = $template->extractVariables();
        $previewVariables = $sampleVariables;

        foreach ($requiredVariables as $variable) {
            if (!isset($previewVariables[$variable])) {
                $previewVariables[$variable] = "[{$variable}]";
            }
        }

        return $template->render($previewVariables);
    }

    /**
     * Validate template content for syntax and structure
     */
    public function validateTemplateContent(array $data): array
    {
        $errors = [];

        // Validate HTML content if provided
        if (!empty($data['html_content'])) {
            $htmlErrors = $this->validateHtmlContent($data['html_content']);
            if (!empty($htmlErrors)) {
                $errors['html_content'] = $htmlErrors;
            }
        }

        // Validate variable syntax in all content fields
        $contentFields = ['subject', 'html_content', 'text_content'];
        foreach ($contentFields as $field) {
            if (!empty($data[$field])) {
                $variableErrors = $this->validateVariableSyntax($data[$field]);
                if (!empty($variableErrors)) {
                    $errors[$field] = $variableErrors;
                }
            }
        }

        // Validate that variables array matches extracted variables
        if (isset($data['variables']) && is_array($data['variables'])) {
            $tempTemplate = new EmailTemplate($data);
            $extractedVariables = $tempTemplate->extractVariables();

            if (array_diff($data['variables'], $extractedVariables)) {
                $errors['variables'] = 'Variables array does not match variables found in template content.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(
                validator([], []),
                $errors
            );
        }

        return $errors;
    }

    /**
     * Validate HTML content structure
     */
    protected function validateHtmlContent(string $htmlContent): array
    {
        $errors = [];

        // Check for basic HTML structure
        if (!str_contains($htmlContent, '<html') && !str_contains($htmlContent, '<body')) {
            // Allow partial HTML content, but warn about missing structure
            // This is not an error, just a recommendation
        }

        // Check for potentially dangerous tags
        $dangerousTags = ['<script', '<iframe', '<object', '<embed', '<form'];
        foreach ($dangerousTags as $tag) {
            if (stripos($htmlContent, $tag) !== false) {
                $errors[] = "Potentially dangerous HTML tag detected: {$tag}";
            }
        }

        // Validate HTML syntax using DOMDocument
        if (class_exists('DOMDocument')) {
            $dom = new \DOMDocument();
            $previousSetting = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $errors_xml = libxml_get_errors();

            if (!empty($errors_xml)) {
                foreach ($errors_xml as $error) {
                    if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                        $errors[] = "HTML syntax error: " . trim($error->message);
                    }
                }
            }

            libxml_use_internal_errors($previousSetting);
        }

        return $errors;
    }

    /**
     * Validate variable syntax in content
     */
    protected function validateVariableSyntax(string $content): array
    {
        $errors = [];

        // Check for unmatched braces
        $openBraces = substr_count($content, '{{');
        $closeBraces = substr_count($content, '}}');

        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unmatched variable braces detected. Each {{ must have a corresponding }}.';
        }

        // Check for nested braces
        if (preg_match('/\{\{[^}]*\{\{/', $content)) {
            $errors[] = 'Nested variable braces are not allowed.';
        }

        // Check for empty variables
        if (preg_match('/\{\{\s*\}\}/', $content)) {
            $errors[] = 'Empty variable placeholders are not allowed.';
        }

        // Check for invalid variable names
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $variable) {
                $variable = trim($variable);
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable)) {
                    $errors[] = "Invalid variable name: '{$variable}'. Variables must start with a letter or underscore and contain only letters, numbers, and underscores.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate template data using Laravel validation
     */
    protected function validateTemplateData(array $data, ?int $excludeId = null): void
    {
        $rules = EmailTemplate::validationRules();

        // Add unique name rule for create/update
        if ($excludeId) {
            $rules['name'] = $rules['name'] . '|unique:email_templates,name,' . $excludeId . ',id,version,' . ($data['version'] ?? 1);
        } else {
            $rules['name'] = $rules['name'] . '|unique:email_templates,name,NULL,id,version,' . ($data['version'] ?? 1);
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Activate a specific template version
     */
    public function activateTemplate(int $templateId): EmailTemplate
    {
        $template = $this->findTemplate($templateId);

        return DB::transaction(function () use ($template) {
            // Deactivate all other versions of this template
            EmailTemplate::where('name', $template->name)
                        ->where('id', '!=', $template->id)
                        ->update(['is_active' => false]);

            // Activate this version
            $template->update(['is_active' => true]);

            return $template->fresh();
        });
    }

    /**
     * Duplicate an existing template with a new name
     */
    public function duplicateTemplate(int $templateId, string $newName, ?string $newDescription = null): EmailTemplate
    {
        $originalTemplate = $this->findTemplate($templateId);

        $data = $originalTemplate->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $data['name'] = $newName;
        $data['version'] = 1;
        $data['parent_id'] = null;

        if ($newDescription !== null) {
            $data['description'] = $newDescription;
        }

        return $this->createTemplate($data);
    }

    /**
     * Get template usage statistics
     */
    public function getTemplateStats(int $templateId): array
    {
        $template = $this->findTemplate($templateId);

        // This would typically query email logs to get usage statistics
        // For now, return basic template information
        return [
            'template_id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'version' => $template->version,
            'is_active' => $template->is_active,
            'variables_count' => count($template->variables ?? []),
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
            // These would be calculated from email_logs table
            'total_sent' => 0,
            'last_used' => null,
        ];
    }

    /**
     * Search templates by content
     */
    public function searchTemplates(string $query, array $filters = []): Collection
    {
        $queryBuilder = EmailTemplate::query();

        // Apply content search
        $queryBuilder->where(function ($q) use ($query) {
            $q->where('name', 'like', '%' . $query . '%')
              ->orWhere('subject', 'like', '%' . $query . '%')
              ->orWhere('html_content', 'like', '%' . $query . '%')
              ->orWhere('text_content', 'like', '%' . $query . '%')
              ->orWhere('description', 'like', '%' . $query . '%');
        });

        // Apply additional filters
        if (!empty($filters['type'])) {
            $queryBuilder->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $queryBuilder->where('is_active', $filters['is_active']);
        }

        return $queryBuilder->orderBy('name')
                          ->orderBy('version', 'desc')
                          ->get();
    }
}
