<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'course_info' => [
                'id' => $this->resource['course_info']['id'],
                'code' => $this->resource['course_info']['code'],
                'name' => $this->resource['course_info']['name'],
                'credit_points' => $this->resource['course_info']['credit_points'],
                'section_code' => $this->resource['course_info']['section_code'],
                'semester' => $this->resource['course_info']['semester'],
                'lecturer' => $this->resource['course_info']['lecturer'],
            ],
            'schedule' => $this->formatSchedule($this->resource['schedule']),
            'grades' => $this->formatGrades($this->resource['grades']),
            'summary' => $this->generateSummary(),
        ];
    }

    /**
     * Format schedule data with consistent structure
     */
    protected function formatSchedule(array $schedule): array
    {
        return collect($schedule)->map(function ($session) {
            return [
                'id' => $session['id'],
                'session_title' => $session['session_title'],
                'session_date' => $session['session_date'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'duration_minutes' => $session['duration_minutes'],
                'session_type' => $session['session_type'],
                'delivery_mode' => $session['delivery_mode'],
                'status' => $session['status'],
                'room' => $session['room'],
                'learning_objectives' => $session['learning_objectives'],
                'required_materials' => $session['required_materials'],
                'topics_covered' => $session['topics_covered'],
                'online_meeting_url' => $session['online_meeting_url'],
                'student_instructions' => $session['student_instructions'],
                'formatted_time' => $this->formatTimeSlot($session['start_time'], $session['end_time']),
                'formatted_date' => $this->formatDate($session['session_date']),
            ];
        })->toArray();
    }

    /**
     * Format grades data with status information
     */
    protected function formatGrades(array $grades): array
    {
        return collect($grades)->map(function ($component) {
            $totalWeight = 0;
            $weightedScore = 0;
            $availableAssessments = 0;
            $completedAssessments = 0;

            $assessments = collect($component['assessments'])->map(function ($assessment) use (&$totalWeight, &$weightedScore, &$availableAssessments, &$completedAssessments) {
                $availableAssessments++;
                $totalWeight += $assessment['weight'];

                if ($assessment['score']['status'] === 'released') {
                    $completedAssessments++;
                    if ($assessment['score']['percentage_score']) {
                        $weightedScore += ($assessment['score']['percentage_score'] * $assessment['weight'] / 100);
                    }
                }

                return [
                    'id' => $assessment['id'],
                    'name' => $assessment['name'],
                    'description' => $assessment['description'],
                    'weight' => $assessment['weight'],
                    'max_points' => $assessment['max_points'],
                    'due_date' => $assessment['due_date'],
                    'score' => $assessment['score'],
                    'submission' => $assessment['submission'],
                    'feedback' => $assessment['feedback'],
                    'formatted_due_date' => $this->formatDate($assessment['due_date']),
                ];
            })->toArray();

            return [
                'component_id' => $component['component_id'],
                'component_name' => $component['component_name'],
                'component_code' => $component['component_code'],
                'component_type' => $component['component_type'],
                'component_weight' => $component['component_weight'],
                'due_date' => $component['due_date'],
                'assessments' => $assessments,
                'component_summary' => [
                    'total_assessments' => $availableAssessments,
                    'completed_assessments' => $completedAssessments,
                    'pending_assessments' => $availableAssessments - $completedAssessments,
                    'component_score' => $totalWeight > 0 ? round($weightedScore, 2) : null,
                    'completion_rate' => $availableAssessments > 0 ? round(($completedAssessments / $availableAssessments) * 100, 1) : 0,
                ],
            ];
        })->toArray();
    }

    /**
     * Generate course summary statistics
     */
    protected function generateSummary(): array
    {
        $schedule = $this->resource['schedule'] ?? [];
        $grades = $this->resource['grades'] ?? [];

        $totalSessions = count($schedule);
        $completedSessions = collect($schedule)->where('status', 'completed')->count();
        $upcomingSessions = collect($schedule)->where('session_date', '>=', now()->toDateString())->count();

        $totalAssessments = 0;
        $completedAssessments = 0;
        $totalComponentWeight = 0;
        $weightedGradeSum = 0;

        foreach ($grades as $component) {
            $totalComponentWeight += $component['component_weight'];

            foreach ($component['assessments'] as $assessment) {
                $totalAssessments++;

                if ($assessment['score']['status'] === 'released') {
                    $completedAssessments++;

                    if ($assessment['score']['percentage_score']) {
                        $weightedGradeSum += ($assessment['score']['percentage_score'] * $component['component_weight'] / 100);
                    }
                }
            }
        }

        $overallGrade = $totalComponentWeight > 0 ? round($weightedGradeSum, 2) : null;

        return [
            'schedule_summary' => [
                'total_sessions' => $totalSessions,
                'completed_sessions' => $completedSessions,
                'upcoming_sessions' => $upcomingSessions,
                'attendance_progress' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0,
            ],
            'grade_summary' => [
                'total_assessments' => $totalAssessments,
                'completed_assessments' => $completedAssessments,
                'pending_assessments' => $totalAssessments - $completedAssessments,
                'overall_grade' => $overallGrade,
                'completion_rate' => $totalAssessments > 0 ? round(($completedAssessments / $totalAssessments) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Format time slot for display
     */
    protected function formatTimeSlot(?string $startTime, ?string $endTime): string
    {
        if (!$startTime || !$endTime) {
            return 'TBD';
        }

        try {
            $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
            $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);

            return $start->format('g:i A') . ' - ' . $end->format('g:i A');
        } catch (\Exception $e) {
            return $startTime . ' - ' . $endTime;
        }
    }

    /**
     * Format date for display
     */
    protected function formatDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M j, Y');
        } catch (\Exception $e) {
            return $date;
        }
    }
}
