<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Progression\Queries\GetTranscriptDerivedEvidenceAuditQuery;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class AuditTranscriptDerivedEvidenceCommand extends Command
{
    protected $signature = 'academic:audit-transcript-derived
        {--student-id= : Restrict to one Student primary key}
        {--semester-id= : Restrict to one Semester primary key}
        {--all : Explicitly audit every final legacy Course Result}
        {--format=table : Output format: table or json}';

    protected $description = 'Read-only audit of transcript-derived GPA, standing, and best-attempt evidence.';

    public function __construct(private readonly GetTranscriptDerivedEvidenceAuditQuery $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $scope = [
                'student_id' => $this->positiveIdOption('student-id'),
                'semester_id' => $this->positiveIdOption('semester-id'),
                'all' => (bool) $this->option('all'),
            ];
            if ($scope['all'] && ($scope['student_id'] !== null || $scope['semester_id'] !== null)) {
                throw new InvalidArgumentException('--all cannot be combined with --student-id or --semester-id.');
            }
            $format = $this->formatOption();
            $report = $this->audit->handle($scope);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($format === 'json') {
            try {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error("Unable to encode the audit report: {$exception->getMessage()}");

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->warn('READ-ONLY: no source, transcript, GPA, standing, or lifecycle data was modified.');
        $this->table(
            ['Metric', 'Count'],
            collect($report['counts'])->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );

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

    private function formatOption(): string
    {
        $format = $this->option('format');
        if (! is_string($format) || ! in_array($format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('--format must be table or json.');
        }

        return $format;
    }
}
