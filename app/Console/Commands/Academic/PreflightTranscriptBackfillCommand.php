<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Progression\Queries\GetTranscriptBackfillPreflightQuery;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class PreflightTranscriptBackfillCommand extends Command
{
    protected $signature = 'academic:preflight-transcript-backfill
        {--student-id= : Restrict to one Student primary key}
        {--semester-id= : Restrict to one Semester primary key}
        {--all : Explicitly inspect every final legacy Course Result}
        {--format=table : Output format: table or json}';

    protected $description = 'Read-only preflight for the approval-gated historical transcript backfill.';

    public function __construct(private readonly GetTranscriptBackfillPreflightQuery $preflight)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error("Unsupported format '{$format}'. Use table or json.");

            return self::FAILURE;
        }

        try {
            $scope = [
                'student_id' => $this->positiveIdOption('student-id'),
                'semester_id' => $this->positiveIdOption('semester-id'),
                'all' => (bool) $this->option('all'),
            ];
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if ($scope['all'] && ($scope['student_id'] !== null || $scope['semester_id'] !== null)) {
            $this->error('--all cannot be combined with --student-id or --semester-id.');

            return self::FAILURE;
        }

        try {
            $report = $this->preflight->handle($scope);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($format === 'json') {
            try {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->warn('READ-ONLY: no academic_records or transcript_entries were modified.');
        $this->table(
            ['Metric', 'Count'],
            collect($report['counts'])->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );
        $this->line('Stable identifier: '.$report['scope']['stable_identifier']);
        $this->line('Run with --format=json to capture every exception row for the human approval checkpoint.');

        return self::SUCCESS;
    }

    private function positiveIdOption(string $option): ?int
    {
        $value = $this->option($option);
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException("--{$option} must be a positive integer.");
        }

        return (int) $value;
    }
}
