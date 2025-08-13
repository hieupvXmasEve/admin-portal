<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateVersioningService;
use Illuminate\Console\Command;

class ManageEmailTemplateVersions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email-template:manage
                            {action : The action to perform (list|versions|rollback|archive|duplicate)}
                            {--template= : Template name}
                            {--template-version= : Version number}
                            {--new-name= : New name for duplicate}
                            {--keep= : Number of versions to keep when archiving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage email template versions';

    /**
     * Email template versioning service
     */
    protected EmailTemplateVersioningService $versioningService;

    /**
     * Create a new command instance.
     */
    public function __construct(EmailTemplateVersioningService $versioningService)
    {
        parent::__construct();
        $this->versioningService = $versioningService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listTemplates(),
            'versions' => $this->showVersions(),
            'rollback' => $this->rollbackVersion(),
            'archive' => $this->archiveVersions(),
            'duplicate' => $this->duplicateTemplate(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * List all email templates
     */
    protected function listTemplates(): int
    {
        $templates = EmailTemplate::select('name', 'type', 'version', 'is_active', 'created_at')
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No email templates found.');
            return 0;
        }

        $this->table(
            ['Name', 'Type', 'Version', 'Active', 'Created'],
            $templates->map(function ($template) {
                return [
                    $template->name,
                    $template->type,
                    $template->version,
                    $template->is_active ? 'Yes' : 'No',
                    $template->created_at->format('Y-m-d H:i:s'),
                ];
            })
        );

        return 0;
    }

    /**
     * Show versions for a specific template
     */
    protected function showVersions(): int
    {
        $templateName = $this->option('template');

        if (!$templateName) {
            $this->error('Template name is required. Use --template option.');
            return 1;
        }

        $versions = $this->versioningService->getTemplateVersions($templateName);

        if ($versions->isEmpty()) {
            $this->error("No versions found for template: {$templateName}");
            return 1;
        }

        $this->info("Versions for template: {$templateName}");
        $this->table(
            ['Version', 'Active', 'Created', 'Updated', 'Description'],
            $versions->map(function ($version) {
                return [
                    $version->version,
                    $version->is_active ? 'Yes' : 'No',
                    $version->created_at->format('Y-m-d H:i:s'),
                    $version->updated_at->format('Y-m-d H:i:s'),
                    $version->description ?? 'N/A',
                ];
            })
        );

        return 0;
    }

    /**
     * Rollback to a specific version
     */
    protected function rollbackVersion(): int
    {
        $templateName = $this->option('template');
        $version = $this->option('template-version');

        if (!$templateName || !$version) {
            $this->error('Template name and version are required. Use --template and --template-version options.');
            return 1;
        }

        try {
            $rolledBackTemplate = $this->versioningService->rollbackToVersion($templateName, (int) $version);
            $this->info("Successfully rolled back '{$templateName}' to version {$version}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to rollback template: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Archive old versions
     */
    protected function archiveVersions(): int
    {
        $templateName = $this->option('template');
        $keepVersions = $this->option('keep') ?? 10;

        if (!$templateName) {
            $this->error('Template name is required. Use --template option.');
            return 1;
        }

        try {
            $archivedCount = $this->versioningService->archiveOldVersions($templateName, (int) $keepVersions);
            $this->info("Archived {$archivedCount} old versions of '{$templateName}', keeping {$keepVersions} most recent versions.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to archive versions: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Duplicate a template
     */
    protected function duplicateTemplate(): int
    {
        $templateName = $this->option('template');
        $newName = $this->option('new-name');

        if (!$templateName || !$newName) {
            $this->error('Template name and new name are required. Use --template and --new-name options.');
            return 1;
        }

        try {
            $currentTemplate = $this->versioningService->getCurrentVersion($templateName);

            if (!$currentTemplate) {
                $this->error("Template '{$templateName}' not found.");
                return 1;
            }

            $duplicatedTemplate = $this->versioningService->duplicateTemplate($currentTemplate, $newName);
            $this->info("Successfully duplicated '{$templateName}' as '{$newName}'");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to duplicate template: {$e->getMessage()}");
            return 1;
        }
    }
}
