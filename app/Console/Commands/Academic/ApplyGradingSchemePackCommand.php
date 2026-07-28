<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Delivery\Actions\ApplyGradingSchemePackAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Dry-run-first CLI wrapper that applies a grading scheme pack to the syllabus
 * templates listed in a school-database-specific mapping JSON file.
 *
 * Operators run --dry-run first to confirm every mapping resolves to exactly one
 * template, then rerun with --commit. The command writes only the current
 * database; schools share the repo, not the database.
 */
final class ApplyGradingSchemePackCommand extends Command
{
    protected $signature = 'academic:apply-grading-scheme-pack
        {--pack=metropolia : Scheme pack identifier (only metropolia is supported)}
        {--mapping= : Absolute path to the mapping JSON file}
        {--dry-run : Validate and report without saving}
        {--commit : Save schemes to matched templates}';

    protected $description = 'Apply a grading scheme pack to local syllabus templates';

    public function handle(ApplyGradingSchemePackAction $action): int
    {
        $pack = (string) $this->option('pack');
        $mappingPath = (string) $this->option('mapping');
        $commit = (bool) $this->option('commit');
        $dryRun = (bool) $this->option('dry-run');

        if ($pack !== 'metropolia') {
            $this->error("Unknown pack [{$pack}]. Only [metropolia] is supported.");

            return self::FAILURE;
        }

        if ($mappingPath === '' || ! is_file($mappingPath)) {
            $this->error('A readable --mapping JSON path is required.');

            return self::FAILURE;
        }

        if ($commit === $dryRun) {
            $this->error('Pass exactly one of --dry-run or --commit.');

            return self::FAILURE;
        }

        try {
            $mappings = json_decode((string) file_get_contents($mappingPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error("Mapping file is not valid JSON: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($mappings)) {
            $this->error('Mapping file must contain a JSON array of mappings.');

            return self::FAILURE;
        }

        $result = $action->execute($mappings, $commit);

        $this->table(['mode', 'total', 'updated', 'skipped', 'failed'], [[
            $commit ? 'commit' : 'dry-run',
            $result['total'],
            $result['updated'],
            $result['skipped'],
            count($result['errors']),
        ]]);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        Log::info('academic:apply-grading-scheme-pack completed', [
            'pack' => $pack,
            'mode' => $commit ? 'commit' : 'dry-run',
            'total' => $result['total'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'failed' => count($result['errors']),
        ]);

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
