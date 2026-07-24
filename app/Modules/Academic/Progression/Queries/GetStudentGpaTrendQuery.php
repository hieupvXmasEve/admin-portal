<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Shared\Contracts\Academic\DTO\StudentGpaTrend;
use App\Shared\Contracts\Academic\StudentAcademicRecordsReader;
use App\Shared\Contracts\Academic\StudentGpaTrendReader;
use Illuminate\Support\Collection;

final class GetStudentGpaTrendQuery implements StudentGpaTrendReader
{
    public function __construct(private readonly StudentAcademicRecordsReader $academicRecords) {}

    public function forStudent(int $studentId, int $semesterCount): StudentGpaTrend
    {
        $gpaCalculations = collect($this->academicRecords->forStudent($studentId)->gpaTrendHistory())
            ->sortByDesc('created_at')
            ->take($semesterCount)
            ->reverse()
            ->values();

        if ($gpaCalculations->isEmpty()) {
            return new StudentGpaTrend([
                'trend_data' => [],
                'trend_analysis' => [
                    'direction' => 'no_data',
                    'consistency' => 'no_data',
                    'improvement_rate' => 0,
                ],
                'predictions' => [],
            ]);
        }

        return new StudentGpaTrend([
            'trend_data' => $this->trendData($gpaCalculations),
            'trend_analysis' => $this->analyze($gpaCalculations),
            'predictions' => $this->predict($gpaCalculations),
        ]);
    }

    /** @param Collection<int, array<string, mixed>> $gpaCalculations
     * @return list<array<string, mixed>>
     */
    private function trendData(Collection $gpaCalculations): array
    {
        return $gpaCalculations->map(static fn (array $calculation): array => [
            'semester' => $calculation['semester'],
            'semester_code' => $calculation['semester_code'],
            'gpa' => round((float) $calculation['gpa'], 2),
            'credit_hours' => (float) $calculation['credit_hours'],
            'quality_points' => (float) $calculation['quality_points'],
            'academic_standing' => $calculation['academic_standing'],
            'date' => $calculation['created_at'] === null ? null : substr($calculation['created_at'], 0, 10),
        ])->all();
    }

    /** @param Collection<int, array<string, mixed>> $gpaCalculations
     * @return array<string, float|string>
     */
    private function analyze(Collection $gpaCalculations): array
    {
        $gpas = $gpaCalculations->map(static fn (array $calculation): float => (float) $calculation['gpa']);

        if ($gpas->count() < 2) {
            return [
                'direction' => 'insufficient_data',
                'consistency' => 'insufficient_data',
                'improvement_rate' => 0,
            ];
        }

        $latest = (float) $gpas->last();
        $previous = (float) $gpas->get($gpas->count() - 2);
        $change = $latest - $previous;

        return [
            'direction' => $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable'),
            'consistency' => $this->consistency($gpas),
            'improvement_rate' => round($change, 2),
            'average_gpa' => round((float) $gpas->avg(), 2),
            'highest_gpa' => round((float) $gpas->max(), 2),
            'lowest_gpa' => round((float) $gpas->min(), 2),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $gpaCalculations
     * @return array<string, float|string|null>
     */
    private function predict(Collection $gpaCalculations): array
    {
        $gpas = $gpaCalculations->map(static fn (array $calculation): float => (float) $calculation['gpa']);

        if ($gpas->count() < 3) {
            return [
                'next_semester_prediction' => null,
                'confidence' => 'low',
                'recommendation' => 'Insufficient data for prediction',
            ];
        }

        $count = $gpas->count();
        $x = range(1, $count);
        $y = $gpas->all();
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(static fn (int $index): float => $x[$index] * $y[$index], range(0, $count - 1)));
        $sumX2 = array_sum(array_map(static fn (int $value): int => $value * $value, $x));
        $slope = ($count * $sumXY - $sumX * $sumY) / ($count * $sumX2 - $sumX * $sumX);
        $prediction = max(0, min(100, $slope * ($count + 1) + (($sumY - $slope * $sumX) / $count)));

        return [
            'next_semester_prediction' => round($prediction, 2),
            'confidence' => $this->predictionConfidence($gpas),
            'recommendation' => $this->recommendation($slope),
        ];
    }

    /** @param Collection<int, float> $gpas */
    private function consistency(Collection $gpas): string
    {
        if ($gpas->count() < 3) {
            return 'insufficient_data';
        }

        $mean = (float) $gpas->avg();
        $variance = (float) $gpas->map(static fn (float $gpa): float => ($gpa - $mean) ** 2)->avg();
        $standardDeviation = sqrt($variance);

        return match (true) {
            $standardDeviation < 0.2 => 'very_consistent',
            $standardDeviation < 0.4 => 'consistent',
            $standardDeviation < 0.6 => 'moderate',
            default => 'inconsistent',
        };
    }

    /** @param Collection<int, float> $gpas */
    private function predictionConfidence(Collection $gpas): string
    {
        return match ($this->consistency($gpas)) {
            'very_consistent', 'consistent' => 'high',
            'moderate' => 'medium',
            default => 'low',
        };
    }

    private function recommendation(float $slope): string
    {
        return match (true) {
            $slope > 0.1 => 'Great progress! Continue with current study strategies.',
            $slope < -0.1 => 'Consider seeking academic support to improve performance.',
            default => 'Maintain your current academic performance.',
        };
    }
}
