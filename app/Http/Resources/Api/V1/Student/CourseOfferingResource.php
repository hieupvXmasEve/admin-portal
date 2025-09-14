<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseOfferingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'unit' => [
                'code' => $this->resource['unit']['code'],
                'name' => $this->resource['unit']['name'],
                'description' => $this->resource['unit']['description'],
                'credit_points' => $this->resource['unit']['credit_points'],
            ],
            'lecturer' => [
                'id' => $this->resource['lecturer']['id'],
                'name' => $this->resource['lecturer']['name'],
                'email' => $this->resource['lecturer']['email'],
            ],
            // 'schedule' => $this->formatSchedule($this->resource['schedule']),
            // 'enrollment' => [
            //     'current' => $this->resource['enrollment']['current'],
            //     'maximum' => $this->resource['enrollment']['maximum'],
            //     'available' => $this->resource['enrollment']['available'],
            //     'waitlist_available' => $this->resource['enrollment']['waitlist_available'],
            //     'enrollment_status' => $this->getEnrollmentStatus($this->resource['enrollment']),
            // ],
//            'registration_eligibility' => [
//                'can_register' => $this->resource['registration_eligibility']['can_register'],
//                'prerequisites_met' => $this->resource['registration_eligibility']['prerequisites_met'],
//                'has_conflicts' => $this->resource['registration_eligibility']['has_conflicts'],
//                'capacity_available' => $this->resource['registration_eligibility']['capacity_available'],
//                'eligibility_summary' => $this->getEligibilitySummary($this->resource['registration_eligibility']),
//            ],
        ];
    }

    /**
     * Format schedule data for display
     */
    protected function formatSchedule($schedule): array
    {
        return collect($schedule)->map(function ($session) {
            // Handle both array and object formats
            $dayOfWeek = is_array($session) ? $session['day_of_week'] : $session->day_of_week;
            $startTime = is_array($session) ? $session['start_time'] : $session->start_time;
            $endTime = is_array($session) ? $session['end_time'] : $session->end_time;
            $room = is_array($session) ? $session['room'] : $session->room;

            // Format times consistently
            $formattedStartTime = $this->formatTime($startTime);
            $formattedEndTime = $this->formatTime($endTime);

            // Handle room data (could be null, array, or object)
            $roomData = [];
            if ($room) {
                $buildingInfo = null;
                if (is_array($room)) {
                    $buildingInfo = $room['building'] ?? null;
                } else {
                    $buildingInfo = $room->building ?? null;
                }

                $roomData = [
                    'code' => is_array($room) ? ($room['code'] ?? null) : ($room->code ?? null),
                    'name' => is_array($room) ? ($room['name'] ?? null) : ($room->name ?? null),
                    'building' => $buildingInfo,
                ];
                $roomData['full_location'] = $this->formatRoomLocation($roomData);
            }

            return [
                'day_of_week' => $dayOfWeek,
                'day_display' => ucfirst($dayOfWeek ?? ''),
                'start_time' => $formattedStartTime,
                'end_time' => $formattedEndTime,
                'duration' => $this->calculateDuration($startTime, $endTime),
                'room' => $roomData,
            ];
        })->toArray();
    }

    /**
     * Get enrollment status description
     */
    protected function getEnrollmentStatus(array $enrollment): string
    {
        $percentage = $enrollment['maximum'] > 0
            ? ($enrollment['current'] / $enrollment['maximum']) * 100
            : 0;

        return match (true) {
            $enrollment['available'] === 0 => 'full',
            $percentage >= 90 => 'nearly_full',
            $percentage >= 75 => 'filling_up',
            $percentage >= 50 => 'half_full',
            $percentage >= 25 => 'available',
            default => 'open',
        };
    }

    /**
     * Get eligibility summary
     */
    protected function getEligibilitySummary(array $eligibility): string
    {
        if ($eligibility['can_register']) {
            return 'eligible';
        }

        $reasons = [];

        if (! $eligibility['prerequisites_met']) {
            $reasons[] = 'prerequisites not met';
        }

        if ($eligibility['has_conflicts']) {
            $reasons[] = 'schedule conflicts';
        }

        if (! $eligibility['capacity_available']) {
            $reasons[] = 'course full';
        }

        return 'not eligible: '.implode(', ', $reasons);
    }

    /**
     * Format time consistently (handle both strings and Carbon instances)
     */
    protected function formatTime($time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            if ($time instanceof \Carbon\Carbon) {
                return $time->format('H:i');
            }

            // If it's a string, try to parse and format it
            return \Carbon\Carbon::createFromTimeString($time)->format('H:i');
        } catch (\Exception $e) {
            // If parsing fails, return the original value as string
            return (string) $time;
        }
    }

    /**
     * Calculate session duration in minutes
     */
    protected function calculateDuration($startTime, $endTime): float
    {
        if (! $startTime || ! $endTime) {
            return 0;
        }

        try {
            // Handle both string and Carbon instances
            if ($startTime instanceof \Carbon\Carbon) {
                $start = $startTime;
            } else {
                $start = \Carbon\Carbon::createFromTimeString($startTime);
            }

            if ($endTime instanceof \Carbon\Carbon) {
                $end = $endTime;
            } else {
                $end = \Carbon\Carbon::createFromTimeString($endTime);
            }

            return $start->diffInMinutes($end);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format room location for display
     */
    protected function formatRoomLocation(array $room): string
    {
        $buildingName = null;
        if (isset($room['building'])) {
            if (is_array($room['building'])) {
                $buildingName = $room['building']['name'] ?? null;
            } else {
                $buildingName = $room['building'];
            }
        }

        $parts = array_filter([
            $room['code'] ?? null,
            $room['name'] ?? null,
            $buildingName,
        ]);

        return implode(' - ', $parts);
    }
}
