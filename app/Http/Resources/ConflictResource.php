<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConflictResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'conflicting_course_id' => $this->id,
            'unit' => [
                'id' => $this->curriculumUnit?->unit?->id,
                'code' => $this->curriculumUnit?->unit?->code,
                'name' => $this->curriculumUnit?->unit?->name,
                'credit_points' => $this->curriculumUnit?->unit?->credit_points,
            ],
            'section_code' => $this->section_code,
            'semester' => [
                'id' => $this->semester?->id,
                'name' => $this->semester?->name,
                'code' => $this->semester?->code,
            ],
            'schedule' => [
                'days' => $this->schedule_days ?? [],
                'time_start' => $this->schedule_time_start?->format('H:i'),
                'time_end' => $this->schedule_time_end?->format('H:i'),
                'location' => $this->location,
            ],
            'delivery_mode' => $this->delivery_mode,
            'enrollment' => [
                'current' => $this->current_enrollment ?? 0,
                'maximum' => $this->max_capacity,
            ],
            'conflict_type' => $this->conflict_type ?? 'time_overlap',
            'conflict_severity' => $this->getConflictSeverity(),
            'conflict_description' => $this->getConflictDescription(),
            'resolution_suggestions' => $this->getResolutionSuggestions(),
        ];
    }

    /**
     * Determine conflict severity
     */
    private function getConflictSeverity(): string
    {
        $conflictType = $this->conflict_type ?? 'time_overlap';

        return match ($conflictType) {
            'same_time' => 'critical',
            'time_overlap' => 'high',
            'adjacent_time' => 'medium',
            'same_day_different_time' => 'low',
            default => 'medium'
        };
    }

    /**
     * Generate human-readable conflict description
     */
    private function getConflictDescription(): string
    {
        $unitCode = $this->curriculumUnit?->unit?->code ?? 'Unknown';
        $unitName = $this->curriculumUnit?->unit?->name ?? 'Unknown Course';
        $sectionCode = $this->section_code ? " (Section {$this->section_code})" : '';
        $timeStart = $this->schedule_time_start?->format('H:i') ?? 'Unknown';
        $timeEnd = $this->schedule_time_end?->format('H:i') ?? 'Unknown';
        $days = is_array($this->schedule_days) ? implode(', ', $this->schedule_days) : 'Unknown days';
        $location = $this->location ? " at {$this->location}" : '';

        $conflictType = $this->conflict_type ?? 'time_overlap';

        return match ($conflictType) {
            'same_time' => "Exact time conflict with {$unitCode} - {$unitName}{$sectionCode} on {$days} from {$timeStart} to {$timeEnd}{$location}",
            'time_overlap' => "Time overlap with {$unitCode} - {$unitName}{$sectionCode} on {$days} from {$timeStart} to {$timeEnd}{$location}",
            'adjacent_time' => "Adjacent time slot with {$unitCode} - {$unitName}{$sectionCode} on {$days} from {$timeStart} to {$timeEnd}{$location}",
            'same_day_different_time' => "Same day scheduling with {$unitCode} - {$unitName}{$sectionCode} on {$days} from {$timeStart} to {$timeEnd}{$location}",
            default => "Schedule conflict with {$unitCode} - {$unitName}{$sectionCode} on {$days} from {$timeStart} to {$timeEnd}{$location}"
        };
    }

    /**
     * Generate resolution suggestions
     */
    private function getResolutionSuggestions(): array
    {
        $suggestions = [];
        $conflictType = $this->conflict_type ?? 'time_overlap';
        $severity = $this->getConflictSeverity();

        switch ($severity) {
            case 'critical':
                $suggestions[] = 'This is a critical conflict - lecturer cannot be in two places at once';
                $suggestions[] = 'Consider assigning a different lecturer to one of the courses';
                $suggestions[] = 'Reschedule one of the course offerings to a different time slot';
                break;

            case 'high':
                $suggestions[] = 'Time overlap detected - may cause scheduling issues';
                $suggestions[] = 'Check if courses can be rescheduled to avoid overlap';
                $suggestions[] = 'Consider if travel time between locations is sufficient';
                $suggestions[] = 'Verify if online delivery mode can resolve the conflict';
                break;

            case 'medium':
                $suggestions[] = 'Potential scheduling concern identified';
                $suggestions[] = 'Review schedule to ensure adequate preparation time';
                $suggestions[] = 'Consider lecturer workload and travel requirements';
                break;

            case 'low':
                $suggestions[] = 'Minor scheduling consideration';
                $suggestions[] = 'Monitor overall daily workload for the lecturer';
                $suggestions[] = 'Ensure adequate break time between sessions';
                break;
        }

        // Add general suggestions
        $suggestions[] = 'Review lecturer preferences and availability';
        $suggestions[] = 'Check campus location compatibility';
        $suggestions[] = 'Consider alternative delivery modes if applicable';

        return $suggestions;
    }
}
