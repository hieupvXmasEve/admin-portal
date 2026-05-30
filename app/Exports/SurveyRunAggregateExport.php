<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyRunAggregateExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $overall
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function __construct(
        private readonly array $header,
        private readonly array $overall,
        private readonly array $sections,
    ) {}

    public function array(): array
    {
        return [
            ...$this->metadataRows(),
            [],
            ...$this->overallRows(),
            [],
            ...$this->sectionRows(),
            [],
            ...$this->questionRows(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:F1')->applyFromArray($this->headerStyle('1F2937', 'E5E7EB'));
        $sheet->getStyle('A13:F13')->applyFromArray($this->headerStyle('FFFFFF', '4A5568'));

        $sectionHeaderRow = count($this->metadataRows()) + 1 + count($this->overallRows()) + 2;
        if ($sheet->getHighestRow() >= $sectionHeaderRow) {
            $sheet->getStyle("A{$sectionHeaderRow}:F{$sectionHeaderRow}")->applyFromArray($this->headerStyle('FFFFFF', '4A5568'));
        }

        $questionHeaderRow = count($this->metadataRows())
            + 1
            + count($this->overallRows())
            + 1
            + count($this->sectionRows())
            + 2;
        if ($sheet->getHighestRow() >= $questionHeaderRow) {
            $sheet->getStyle("A{$questionHeaderRow}:G{$questionHeaderRow}")->applyFromArray($this->headerStyle('FFFFFF', '4A5568'));
        }

        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('G:G')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        return [];
    }

    public function title(): string
    {
        return 'Survey Aggregate';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function metadataRows(): array
    {
        $courseInfo = $this->header['course_info'] ?? null;

        return [
            ['Survey Result Download'],
            ['Form', $this->header['form_title'] ?? 'N/A'],
            ['Form Version', $this->header['form_version'] ?? 'N/A'],
            ['Semester', $this->header['semester'] ?? 'N/A'],
            ['Course Code', $courseInfo['code'] ?? 'N/A'],
            ['Course Name', $courseInfo['name'] ?? 'N/A'],
            ['Section', $courseInfo['section'] ?? 'N/A'],
            ['Instructor', $courseInfo['instructor'] ?? 'N/A'],
            ['Responses', sprintf('%s / %s', $this->header['responses_done'] ?? 0, $this->header['responses_total'] ?? 0)],
            ['Completion %', ($this->header['responses_percent'] ?? 0).'%'],
            ['Downloaded At', now()->format('Y-m-d H:i:s')],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function overallRows(): array
    {
        return [
            ['Overall KPIs'],
            ['Average Rating', $this->overall['average'] ?? 0],
            ['Positive %', ($this->overall['positive_percent'] ?? 0).'%'],
            ['Neutral %', ($this->overall['neutral_percent'] ?? 0).'%'],
            ['Negative %', ($this->overall['negative_percent'] ?? 0).'%'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sectionRows(): array
    {
        $rows = [
            ['Section Ratings'],
            ['Section', 'Average', 'Positive %', 'Neutral %', 'Negative %', 'Distribution'],
        ];

        foreach ($this->sections as $section) {
            $stats = $section['stats'] ?? null;
            if (! is_array($stats)) {
                continue;
            }

            $positive = (int) ($stats['positive_percent'] ?? 0);
            $negative = (int) ($stats['negative_percent'] ?? 0);

            $rows[] = [
                $section['title'] ?? 'Untitled section',
                $stats['average'] ?? 0,
                $positive.'%',
                max(0, 100 - $positive - $negative).'%',
                $negative.'%',
                $this->formatRatingDistribution($stats['distribution'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function questionRows(): array
    {
        $rows = [
            ['Question Results'],
            ['Section', 'Question', 'Type', 'Responses', 'Metric', 'Value', 'Details'],
        ];

        foreach ($this->sections as $section) {
            foreach (($section['questions'] ?? []) as $question) {
                foreach ($this->questionMetricRows($section, $question) as $row) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $question
     * @return array<int, array<int, mixed>>
     */
    private function questionMetricRows(array $section, array $question): array
    {
        $base = [
            $section['title'] ?? 'Untitled section',
            $question['text'] ?? 'Untitled question',
            $this->formatQuestionType((string) ($question['type'] ?? 'unknown')),
            $question['total_responses'] ?? 0,
        ];

        $data = $question['data'] ?? [];
        if (! is_array($data)) {
            return [[...$base, 'Result', 'N/A', '']];
        }

        return match ($question['type'] ?? null) {
            'rating' => [[
                ...$base,
                'Average',
                $data['average'] ?? 0,
                $this->formatRatingDistribution($data['distribution'] ?? []),
            ]],
            'single_choice', 'likert', 'multi_choice' => $this->choiceMetricRows($base, $data),
            'short_text', 'long_text' => $this->textMetricRows($base, $data),
            default => [[...$base, 'Result', 'N/A', 'Unsupported question type']],
        };
    }

    /**
     * @param  array<int, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<int, array<int, mixed>>
     */
    private function choiceMetricRows(array $base, array $data): array
    {
        $options = $data['options'] ?? [];
        if (! is_array($options) || $options === []) {
            return [[...$base, 'Options', '0', 'No options selected']];
        }

        $rows = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $rows[] = [
                ...$base,
                (string) ($option['label'] ?? 'Option'),
                $option['count'] ?? 0,
                ($option['percentage'] ?? 0).'%',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<int, array<int, mixed>>
     */
    private function textMetricRows(array $base, array $data): array
    {
        $responses = $data['responses'] ?? [];
        if (! is_array($responses) || $responses === []) {
            return [[...$base, 'Text responses', 0, 'No text responses']];
        }

        $rows = [];
        foreach (array_values($responses) as $index => $response) {
            $rows[] = [
                ...$base,
                'Text response #'.($index + 1),
                '',
                (string) $response,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $distribution
     */
    private function formatRatingDistribution(array $distribution): string
    {
        $labels = [];
        for ($score = 1; $score <= 5; $score++) {
            $labels[] = "{$score}: ".(int) ($distribution[$score - 1] ?? 0);
        }

        return implode('; ', $labels);
    }

    private function formatQuestionType(string $type): string
    {
        return ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @return array<string, mixed>
     */
    private function headerStyle(string $fontColor, string $fillColor): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $fontColor],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
    }
}
