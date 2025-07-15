<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\ClassSession;
use App\Models\CourseRegistration;
use App\Models\Attendance;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates attendance records for FALL2024 class sessions
     */
    public function run(): void
    {
        $this->command->info('✅ Creating attendance records for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'FALL2024')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        // Get all class sessions for FALL2024
        $classSessions = ClassSession::whereHas('courseOffering', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id);
        })->with(['courseOffering.unit'])->get();

        if ($classSessions->isEmpty()) {
            throw new \Exception('No class sessions found for FALL2024.');
        }

        // Check if attendance records already exist
        $existingAttendance = Attendance::whereIn('class_session_id', $classSessions->pluck('id'))->count();
        if ($existingAttendance > 0) {
            $this->command->info("✅ Attendance records already exist for FALL2024 ({$existingAttendance} found). Skipping creation.");
            return;
        }

        $attendanceCount = 0;

        foreach ($classSessions as $session) {
            $records = $this->createAttendanceForSession($session);
            $attendanceCount += count($records);

            if ($attendanceCount % 100 === 0) {
                $this->command->info("  Created {$attendanceCount} attendance records...");
            }
        }

        $this->command->info("✅ Created {$attendanceCount} attendance records for FALL2024!");
    }

    private function createAttendanceForSession(ClassSession $session): array
    {
        $attendanceRecords = [];

        // Get all students registered for this course offering
        $registrations = CourseRegistration::where('course_offering_id', $session->course_offering_id)
            ->where('registration_status', 'registered') // Only registered students, not waitlisted
            ->with('student')
            ->get();

        foreach ($registrations as $registration) {
            $attendanceStatus = $this->determineAttendanceStatus($session, $registration);

            $attendance = Attendance::create([
                'class_session_id' => $session->id,
                'student_id' => $registration->student_id,
                'recorded_by_lecture_id' => $session->instructor_id,
                'status' => $attendanceStatus,
                'check_in_time' => $this->getCheckInTime($session, $attendanceStatus),
                'check_out_time' => $this->getCheckOutTime($session, $attendanceStatus),
                'notes' => $this->getAttendanceNotes($attendanceStatus),
                'excuse_reason' => $this->getExcuseReason($attendanceStatus),
                'recording_method' => 'manual',
                'affects_grade' => true,
                'is_makeup_allowed' => $this->isExcused($attendanceStatus),
            ]);

            $attendanceRecords[] = $attendance;
        }

        return $attendanceRecords;
    }

    private function determineAttendanceStatus(ClassSession $session, CourseRegistration $registration): string
    {
        // Realistic attendance patterns
        $random = rand(1, 100);

        // First few sessions have higher attendance
        $sessionDate = \Carbon\Carbon::parse($session->session_date);
        $semesterStart = \Carbon\Carbon::parse('2024-08-01'); // Approximate semester start
        $weekNumber = $sessionDate->diffInWeeks($semesterStart) + 1;

        // Attendance rates decrease slightly over time
        $baseAttendanceRate = max(75, 95 - ($weekNumber * 2));

        // Session type affects attendance
        $sessionType = $session->session_type;
        if ($sessionType === 'assessment' || $sessionType === 'exam') {
            $baseAttendanceRate = 95; // High attendance for assessments
        } elseif ($sessionType === 'orientation') {
            $baseAttendanceRate = 90; // Good attendance for orientation
        }

        if ($random <= $baseAttendanceRate) {
            // Present students - some might be late
            if ($random <= $baseAttendanceRate * 0.9) {
                return 'present';
            } else {
                return 'late';
            }
        } else {
            // Absent students - some have excuses
            if ($random <= $baseAttendanceRate + 10) {
                return rand(1, 100) <= 30 ? 'excused' : 'absent';
            } else {
                return 'absent';
            }
        }
    }

    private function getCheckInTime(ClassSession $session, string $status): ?string
    {
        if ($status === 'absent' || $status === 'excused') {
            return null;
        }

        $startTime = \Carbon\Carbon::parse($session->start_time);

        if ($status === 'late') {
            // Late by 5-20 minutes
            $lateMinutes = rand(5, 20);
            return $startTime->addMinutes($lateMinutes)->format('H:i:s');
        } elseif ($status === 'partial') {
            // Arrives very late
            $lateMinutes = rand(30, 60);
            return $startTime->addMinutes($lateMinutes)->format('H:i:s');
        }

        // Present - arrives on time or slightly early
        $earlyMinutes = rand(-5, 5);
        return $startTime->addMinutes($earlyMinutes)->format('H:i:s');
    }

    private function getCheckOutTime(ClassSession $session, string $status): ?string
    {
        if ($status === 'absent' || $status === 'excused') {
            return null;
        }

        $endTime = \Carbon\Carbon::parse($session->end_time);

        if ($status === 'partial') {
            // Leaves early
            $earlyMinutes = rand(15, 45);
            return $endTime->subMinutes($earlyMinutes)->format('H:i:s');
        }

        // Most students stay until the end
        $variationMinutes = rand(-5, 10);
        return $endTime->addMinutes($variationMinutes)->format('H:i:s');
    }

    private function getAttendanceNotes(string $status): ?string
    {
        $notes = [
            'late' => [
                'Traffic delay',
                'Previous class ran late',
                'Transportation issues',
                null, // No note
            ],
            'partial' => [
                'Left early due to appointment',
                'Family emergency',
                'Feeling unwell',
            ],
            'excused' => [
                'Medical appointment',
                'Family emergency',
                'Religious observance',
                'University-approved activity',
            ],
            'absent' => [
                null, // Most absences have no note
                'Illness',
                'Personal reasons',
            ],
        ];

        $statusNotes = $notes[$status] ?? [null];
        return $statusNotes[array_rand($statusNotes)];
    }

    private function isExcused(string $status): bool
    {
        return in_array($status, ['excused', 'medical_leave', 'official_leave']);
    }

    private function getExcuseReason(string $status): ?string
    {
        if (!$this->isExcused($status)) {
            return null;
        }

        $reasons = [
            'Medical appointment',
            'Family emergency',
            'Religious observance',
            'University-approved activity',
            'Illness with medical certificate',
            'Bereavement',
        ];

        return $reasons[array_rand($reasons)];
    }

    private function hasExcuseDocumentation(string $status): bool
    {
        if (!$this->isExcused($status)) {
            return false;
        }

        // 70% of excused absences have documentation
        return rand(1, 100) <= 70;
    }
}
