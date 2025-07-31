<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminScheduleService
{
    /**
     * Get schedule data with filters for grid display
     */
    public function getScheduleData(array $filters): Collection
    {
        $query = ClassSession::with([
            'courseOffering.curriculumUnit.unit',
            'courseOffering.semester',
            'lecture',
            'room.campus',
        ]);

        $this->applyFilters($query, $filters);

        $sessions = $query->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return $this->formatSessionsForGrid($sessions);
    }

    /**
     * Update session details with validation
     */
    public function updateSession(ClassSession $session, array $data): ClassSession
    {
        return DB::transaction(function () use ($session, $data) {
            // Validate conflicts before updating
            $conflicts = $this->validateScheduleConflicts($session, $data);

            if (!empty($conflicts)) {
                throw new \InvalidArgumentException('Schedule conflicts detected: ' . implode(', ', $conflicts));
            }

            // Convert time strings to proper format if needed
            if (isset($data['start_time'])) {
                $data['start_time'] = Carbon::createFromFormat('H:i', $data['start_time']);
            }

            if (isset($data['end_time'])) {
                $data['end_time'] = Carbon::createFromFormat('H:i', $data['end_time']);
            }

            // Calculate duration if both times are provided
            if (isset($data['start_time'], $data['end_time'])) {
                $data['duration_minutes'] = $data['start_time']->diffInMinutes($data['end_time']);
            }

            $session->update($data);

            return $session->fresh();
        });
    }

    /**
     * Validate schedule conflicts for session updates
     */
    public function validateScheduleConflicts(ClassSession $session, array $newData): array
    {
        $conflicts = [];

        // Validate time constraints
        if (isset($newData['start_time'], $newData['end_time'])) {
            $startTime = is_string($newData['start_time'])
                ? Carbon::createFromFormat('H:i', $newData['start_time'])
                : $newData['start_time'];
            $endTime = is_string($newData['end_time'])
                ? Carbon::createFromFormat('H:i', $newData['end_time'])
                : $newData['end_time'];

            if ($endTime <= $startTime) {
                $conflicts['time'] = 'End time must be after start time';
            }
        }

        // Check room conflicts if room is being changed
        if (isset($newData['room_id'])) {
            $roomConflicts = $this->checkRoomConflicts($session, $newData);
            if ($roomConflicts->isNotEmpty()) {
                $conflicts['room'] = 'Room is already booked for this time slot';
            }
        }

        // Check lecturer conflicts
        $lecturerConflicts = $this->checkLecturerConflicts($session, $newData);
        if ($lecturerConflicts->isNotEmpty()) {
            $conflicts['lecturer'] = 'Lecturer has another session at this time';
        }

        return $conflicts;
    }

    /**
     * Get filter options for dropdowns
     */
    public function getFilterOptions(): array
    {
        return [
            'semesters' => Semester::select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($semester) {
                    return [
                        'id' => $semester->id,
                        'name' => $semester->name,
                        'code' => $semester->code,
                        'academic_year' => $semester->start_date ? $semester->start_date->year : null,
                        'start_date' => $semester->start_date?->toDateString(),
                        'end_date' => $semester->end_date?->toDateString(),
                    ];
                }),
            'lecturers' => Lecture::select('id', 'first_name', 'last_name', 'email')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(function ($lecturer) {
                    return [
                        'id' => $lecturer->id,
                        'name' => $lecturer->full_name,
                        'email' => $lecturer->email,
                    ];
                }),
            'rooms' => Room::with('campus:id,name')
                ->select('id', 'name', 'code', 'building', 'campus_id')
                ->orderBy('building')
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['semester_id'])) {
            $query->whereHas('courseOffering', function ($q) use ($filters) {
                $q->where('semester_id', $filters['semester_id']);
            });
        }

        if (!empty($filters['lecturer_id'])) {
            $query->where('lecture_id', $filters['lecturer_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['date_range'])) {
            $dateRange = $filters['date_range'];
            if (!empty($dateRange['start'])) {
                $query->whereDate('session_date', '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->whereDate('session_date', '<=', $dateRange['end']);
            }
        }

        // Filter out cancelled sessions by default
        $query->where('status', '!=', 'cancelled');
    }

    /**
     * Format sessions for grid display
     */
    private function formatSessionsForGrid(Collection $sessions): Collection
    {
        Log::info('Formatting sessions for grid display', [
            '$sessions'=> $sessions
        ]);
        return $sessions->map(function (ClassSession $session) {
            return (object) [
                'id' => $session->id,
                'title' => $session->session_title,
                'unitCode' => $session->courseOffering->curriculumUnit->unit->code ?? 'N/A',
                'section' => $session->courseOffering->section ?? 'A',
                'lecturer' => $session->lecture->full_name ?? 'TBA',
                'room' => $session->room->name ?? 'TBA',
                'startTime' => $session->start_time->format('H:i'),
                'endTime' => $session->end_time->format('H:i'),
                'date' => $session->session_date->toDateString(),
                'campusName' => $session->room->campus->name ?? 'N/A',
                'status' => $session->status,
                'sessionType' => $session->session_type,
                'deliveryMode' => $session->delivery_mode,
            ];
        });
    }

    /**
     * Check for room booking conflicts
     */
    private function checkRoomConflicts(ClassSession $session, array $newData): Collection
    {
        $query = ClassSession::where('room_id', $newData['room_id'])
            ->where('id', '!=', $session->id)
            ->where('status', '!=', 'cancelled');

        if (isset($newData['session_date'])) {
            $query->whereDate('session_date', $newData['session_date']);
        } else {
            $query->whereDate('session_date', $session->session_date);
        }

        // Check for time overlap
        $startTime = isset($newData['start_time'])
            ? (is_string($newData['start_time']) ? $newData['start_time'] : $newData['start_time']->format('H:i'))
            : $session->start_time->format('H:i');
        $endTime = isset($newData['end_time'])
            ? (is_string($newData['end_time']) ? $newData['end_time'] : $newData['end_time']->format('H:i'))
            : $session->end_time->format('H:i');

        $query->where(function ($q) use ($startTime, $endTime) {
            $q->where(function ($subQ) use ($startTime, $endTime) {
                // New session starts during existing session
                $subQ->whereTime('start_time', '<=', $startTime)
                     ->whereTime('end_time', '>', $startTime);
            })->orWhere(function ($subQ) use ($startTime, $endTime) {
                // New session ends during existing session
                $subQ->whereTime('start_time', '<', $endTime)
                     ->whereTime('end_time', '>=', $endTime);
            })->orWhere(function ($subQ) use ($startTime, $endTime) {
                // Existing session is completely within new session
                $subQ->whereTime('start_time', '>=', $startTime)
                     ->whereTime('end_time', '<=', $endTime);
            });
        });

        return $query->get();
    }

    /**
     * Check for lecturer schedule conflicts
     */
    private function checkLecturerConflicts(ClassSession $session, array $newData): Collection
    {
        $query = ClassSession::where('lecture_id', $session->lecture_id)
            ->where('id', '!=', $session->id)
            ->where('status', '!=', 'cancelled');

        if (isset($newData['session_date'])) {
            $query->whereDate('session_date', $newData['session_date']);
        } else {
            $query->whereDate('session_date', $session->session_date);
        }

        // Check for time overlap (same logic as room conflicts)
        $startTime = isset($newData['start_time'])
            ? (is_string($newData['start_time']) ? $newData['start_time'] : $newData['start_time']->format('H:i'))
            : $session->start_time->format('H:i');
        $endTime = isset($newData['end_time'])
            ? (is_string($newData['end_time']) ? $newData['end_time'] : $newData['end_time']->format('H:i'))
            : $session->end_time->format('H:i');

        $query->where(function ($q) use ($startTime, $endTime) {
            $q->where(function ($subQ) use ($startTime, $endTime) {
                $subQ->whereTime('start_time', '<=', $startTime)
                     ->whereTime('end_time', '>', $startTime);
            })->orWhere(function ($subQ) use ($startTime, $endTime) {
                $subQ->whereTime('start_time', '<', $endTime)
                     ->whereTime('end_time', '>=', $endTime);
            })->orWhere(function ($subQ) use ($startTime, $endTime) {
                $subQ->whereTime('start_time', '>=', $startTime)
                     ->whereTime('end_time', '<=', $endTime);
            });
        });

        return $query->get();
    }
}
