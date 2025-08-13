<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EmailTemplateVersioningService
{
    /**
     * Create a new version of an existing template
     */
    public function createNewVersion(EmailTemplate $template, array $attributes): EmailTemplate
    {
        return DB::transaction(function () use ($template, $attributes) {
            // Deactivate current version if this will be the new active version
            if ($attributes['is_active'] ?? false) {
                $this->deactivateCurrentVersions($template->name);
            }

            // Create new version
            $newVersion = $template->createNewVersion($attributes);

            return $newVersion;
        });
    }

    /**
     * Rollback to a previous version of a template
     */
    public function rollbackToVersion(string $templateName, int $version): EmailTemplate
    {
        return DB::transaction(function () use ($templateName, $version) {
            // Find the target version
            $targetTemplate = EmailTemplate::where('name', $templateName)
                ->where('version', $version)
                ->firstOrFail();

            // Deactivate all current versions
            $this->deactivateCurrentVersions($templateName);

            // Activate the target version
            $targetTemplate->update(['is_active' => true]);

            return $targetTemplate;
        });
    }

    /**
     * Get all versions of a template
     */
    public function getTemplateVersions(string $templateName): Collection
    {
        return EmailTemplate::where('name', $templateName)
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Get the current active version of a template
     */
    public function getCurrentVersion(string $templateName): ?EmailTemplate
    {
        return EmailTemplate::where('name', $templateName)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Compare two versions of a template
     */
    public function compareVersions(EmailTemplate $version1, EmailTemplate $version2): array
    {
        return [
            'subject_changed' => $version1->subject !== $version2->subject,
            'html_content_changed' => $version1->html_content !== $version2->html_content,
            'text_content_changed' => $version1->text_content !== $version2->text_content,
            'variables_changed' => $version1->variables !== $version2->variables,
            'changes' => [
                'subject' => [
                    'old' => $version1->subject,
                    'new' => $version2->subject,
                ],
                'html_content' => [
                    'old' => $version1->html_content,
                    'new' => $version2->html_content,
                ],
                'text_content' => [
                    'old' => $version1->text_content,
                    'new' => $version2->text_content,
                ],
                'variables' => [
                    'old' => $version1->variables,
                    'new' => $version2->variables,
                ],
            ],
        ];
    }

    /**
     * Archive old versions (keep only specified number of versions)
     */
    public function archiveOldVersions(string $templateName, int $keepVersions = 10): int
    {
        $versions = EmailTemplate::where('name', $templateName)
            ->where('is_active', false)
            ->orderBy('version', 'desc')
            ->get()
            ->skip($keepVersions)
            ->pluck('id');

        if ($versions->isEmpty()) {
            return 0;
        }

        return EmailTemplate::whereIn('id', $versions)->delete();
    }

    /**
     * Duplicate a template with a new name
     */
    public function duplicateTemplate(EmailTemplate $template, string $newName): EmailTemplate
    {
        return EmailTemplate::create([
            'name' => $newName,
            'type' => $template->type,
            'subject' => $template->subject,
            'html_content' => $template->html_content,
            'text_content' => $template->text_content,
            'variables' => $template->variables,
            'description' => $template->description . ' (Copy)',
            'is_active' => false, // New duplicates start as inactive
            'version' => 1,
        ]);
    }

    /**
     * Preview template changes before creating new version
     */
    public function previewChanges(EmailTemplate $currentTemplate, array $newAttributes): array
    {
        $preview = [
            'current' => [
                'subject' => $currentTemplate->subject,
                'html_content' => $currentTemplate->html_content,
                'text_content' => $currentTemplate->text_content,
                'variables' => $currentTemplate->variables,
            ],
            'proposed' => [
                'subject' => $newAttributes['subject'] ?? $currentTemplate->subject,
                'html_content' => $newAttributes['html_content'] ?? $currentTemplate->html_content,
                'text_content' => $newAttributes['text_content'] ?? $currentTemplate->text_content,
                'variables' => $newAttributes['variables'] ?? $currentTemplate->variables,
            ],
        ];

        $preview['has_changes'] =
            $preview['current']['subject'] !== $preview['proposed']['subject'] ||
            $preview['current']['html_content'] !== $preview['proposed']['html_content'] ||
            $preview['current']['text_content'] !== $preview['proposed']['text_content'] ||
            $preview['current']['variables'] !== $preview['proposed']['variables'];

        return $preview;
    }

    /**
     * Validate template before creating new version
     */
    public function validateTemplateVersion(array $attributes): array
    {
        $errors = [];

        // Validate required fields
        if (empty($attributes['subject'])) {
            $errors['subject'] = 'Subject is required';
        }

        if (empty($attributes['html_content'])) {
            $errors['html_content'] = 'HTML content is required';
        }

        // Validate template syntax
        if (!empty($attributes['html_content'])) {
            $syntaxErrors = $this->validateTemplateSyntax($attributes['html_content']);
            if (!empty($syntaxErrors)) {
                $errors['html_content_syntax'] = $syntaxErrors;
            }
        }

        if (!empty($attributes['text_content'])) {
            $syntaxErrors = $this->validateTemplateSyntax($attributes['text_content']);
            if (!empty($syntaxErrors)) {
                $errors['text_content_syntax'] = $syntaxErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate template syntax for variable placeholders
     */
    private function validateTemplateSyntax(string $content): array
    {
        $errors = [];

        // Check for unmatched braces
        $openBraces = substr_count($content, '{{');
        $closeBraces = substr_count($content, '}}');

        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unmatched template variable braces';
        }

        // Check for invalid variable names
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        foreach ($matches[1] as $variable) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($variable))) {
                $errors[] = "Invalid variable name: {$variable}";
            }
        }

        return $errors;
    }

    /**
     * Deactivate all current versions of a template
     */
    private function deactivateCurrentVersions(string $templateName): void
    {
        EmailTemplate::where('name', $templateName)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Get template usage statistics
     */
    public function getTemplateUsageStats(string $templateName): array
    {
        // This would integrate with EmailLog model when available
        return [
            'total_sent' => 0, // Would query EmailLog
            'last_sent' => null, // Would query EmailLog
            'success_rate' => 0, // Would calculate from EmailLog
            'active_version' => $this->getCurrentVersion($templateName)?->version,
            'total_versions' => EmailTemplate::where('name', $templateName)->count(),
        ];
    }
}
