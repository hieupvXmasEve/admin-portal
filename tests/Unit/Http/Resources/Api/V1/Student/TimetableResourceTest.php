<?php

declare(strict_types=1);

use App\Http\Resources\Api\V1\Student\TimetableResource;

function makeRawSession(int $id): array
{
    return [
        'id' => $id,
        'course_code' => 'CS101',
        'course_name' => 'Intro to Computer Science',
        'session_type' => 'lecture',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'duration_minutes' => 90,
        'lecturer' => 'Dr. Smith',
        'room' => [
            'code' => 'A101',
            'name' => 'Room A101',
            'building' => 'Building A',
        ],
        'color' => '#3B82F6',
    ];
}

test('time block sessions carry the same time/course shape as weekly schedule sessions', function () {
    $rawSession = makeRawSession(1);

    $timetable = [
        'semester' => ['id' => 1, 'name' => 'Fall 2026'],
        'weekly_schedule' => [
            'monday' => [
                'day_name' => 'Monday',
                'day' => 1,
                'day_abbreviation' => 'Mon',
                'event_count' => 0,
                'session_count' => 1,
                'events' => [],
                'sessions' => [$rawSession],
                'total_duration' => ['total_minutes' => 90, 'display' => '1h 30m'],
            ],
        ],
        'schedule_summary' => [
            'overview' => [
                'total_sessions_per_week' => 1,
                'unique_courses' => 1,
                'total_hours_per_week' => 1.5,
                'average_hours_per_day' => 1.5,
            ],
            'schedule_pattern' => [
                'busiest_day' => 'monday',
                'earliest_start' => '08:00:00',
                'latest_end' => '09:30:00',
                'earliest_start_display' => '8:00 AM',
                'latest_end_display' => '9:30 AM',
            ],
            'distribution' => ['by_day' => [], 'by_session_type' => []],
        ],
        'time_blocks' => [
            [
                'time' => '08:00',
                'display_time' => '8:00 AM',
                'sessions' => ['monday' => [$rawSession]],
            ],
        ],
        'filters_applied' => [],
    ];

    $resource = (new TimetableResource($timetable))->toArray(request());

    $weeklySession = $resource['weekly_schedule']['monday']['sessions'][0];
    $timeBlockSession = $resource['time_blocks'][0]['sessions']['monday'][0];

    // Both sources must expose the same SessionItem shape the frontend
    // (DailyView.vue, TimetableSessionCard.vue) reads `session.time.display` and
    // `session.course_name` from — a shape drift here is what threw
    // "Cannot read properties of undefined (reading 'display')" in daily view.
    expect($timeBlockSession['time']['display'])->toBe($weeklySession['time']['display'])
        ->and($timeBlockSession['course_name'])->toBe($weeklySession['course_name'])
        ->and($timeBlockSession['session_type_display'])->toBe($weeklySession['session_type_display'])
        ->and($timeBlockSession['room'])->toBe($weeklySession['room']);
});
