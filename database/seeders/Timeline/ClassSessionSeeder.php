<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\CourseOffering;
use App\Models\ClassSession;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ClassSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates class sessions for FALL2024 course offerings
     */
    public function run(): void
    {
        $this->command->info('🏫 Creating class sessions for FALL2024...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'FALL2024')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found.');
        }

        $courseOfferings = CourseOffering::with(['unit', 'lecture'])
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->get();

        if ($courseOfferings->isEmpty()) {
            throw new \Exception('No FALL2024 course offerings found.');
        }

        // Check if class sessions already exist for this semester
        $existingSessions = ClassSession::whereHas('courseOffering', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id);
        })->count();

        if ($existingSessions > 0) {
            $this->command->info("✅ Class sessions already exist for FALL2024 ({$existingSessions} found). Skipping creation.");
            return;
        }

        $sessionCount = 0;

        foreach ($courseOfferings as $courseOffering) {
            $sessions = $this->createSessionsForOffering($courseOffering, $semester);
            $sessionCount += count($sessions);
        }

        $this->command->info("✅ Created {$sessionCount} class sessions for FALL2024!");
    }

    private function createSessionsForOffering(CourseOffering $courseOffering, Semester $semester): array
    {
        $sessions = [];
        $unit = $courseOffering->unit;

        // Calculate session dates based on semester duration and schedule
        $semesterStart = $semester->start_date;
        $semesterEnd = $semester->end_date;

        // Get scheduled days for this offering
        $scheduleDays = $courseOffering->schedule_days ?? ['Monday', 'Wednesday'];

        // Generate sessions for each scheduled day throughout the semester
        $currentDate = $semesterStart->copy();
        $sessionNumber = 1;

        while ($currentDate->lte($semesterEnd)) {
            $dayName = $currentDate->format('l'); // Full day name

            if (in_array($dayName, $scheduleDays)) {
                // Skip holiday periods (mid-semester break, etc.)
                if (!$this->isHolidayPeriod($currentDate)) {
                    $session = $this->createClassSession($courseOffering, $currentDate, $sessionNumber);
                    $sessions[] = $session;
                    $sessionNumber++;
                }
            }

            $currentDate->addDay();
        }

        $this->command->info("  📚 {$unit->code}: Created " . count($sessions) . " sessions");

        return $sessions;
    }

    private function createClassSession(CourseOffering $courseOffering, Carbon $date, int $sessionNumber): ClassSession
    {
        $unit = $courseOffering->unit;
        $sessionTitle = $this->generateSessionTitle($unit, $sessionNumber);

        return ClassSession::create([
            'course_offering_id' => $courseOffering->id,
            'room_id' => null, // Will be assigned later if needed
            'room_booking_id' => null,
            'instructor_id' => $courseOffering->lecture_id,
            'session_title' => $sessionTitle,
            'session_description' => $this->generateSessionDescription($unit, $sessionNumber),
            'session_date' => $date->toDateString(),
            'start_time' => $this->getValidStartTime($courseOffering),
            'end_time' => $this->getValidEndTime($courseOffering),
            'session_type' => $this->getSessionType($sessionNumber),
            'delivery_mode' => $courseOffering->delivery_mode,
            'status' => 'scheduled',
            'learning_objectives' => json_encode($this->getLearningObjectives($unit, $sessionNumber)),
            'required_materials' => json_encode($this->getSessionMaterials($unit, $sessionNumber)),
            'topics_covered' => json_encode($this->getTopicsCovered($unit, $sessionNumber)),
            'attendance_required' => true,
            'attendance_tracking_enabled' => true,
            'expected_attendees' => $courseOffering->max_capacity,
            'is_assessment' => $this->isAssessmentSession($sessionNumber),
            'sequence_number' => $sessionNumber,
            'instructor_notes' => $this->getPreparationNotes($unit, $sessionNumber),
            'student_instructions' => $this->getStudentInstructions($unit, $sessionNumber),
        ]);
    }

    private function generateSessionTitle(Unit $unit, int $sessionNumber): string
    {
        $unitCode = $unit->code;

        // Define session titles based on unit type and session number
        $sessionTitles = [
            'COS10009' => [
                1 => 'Introduction to Programming Concepts',
                2 => 'Variables and Data Types',
                3 => 'Control Structures: Conditionals',
                4 => 'Control Structures: Loops',
                5 => 'Functions and Methods',
                6 => 'Arrays and Collections',
                7 => 'Object-Oriented Programming Basics',
                8 => 'Classes and Objects',
                9 => 'Inheritance and Polymorphism',
                10 => 'Error Handling and Debugging',
                11 => 'File Input/Output',
                12 => 'Final Project Presentations',
            ],
            'COS10011' => [
                1 => 'Web Development Overview',
                2 => 'HTML Fundamentals',
                3 => 'CSS Styling and Layout',
                4 => 'JavaScript Basics',
                5 => 'DOM Manipulation',
                6 => 'Forms and User Input',
                7 => 'Server-Side Programming Introduction',
                8 => 'Database Integration',
                9 => 'Web Security Basics',
                10 => 'Responsive Design',
                11 => 'Project Development',
                12 => 'Portfolio Presentations',
            ],
        ];

        $titles = $sessionTitles[$unitCode] ?? [];

        if (isset($titles[$sessionNumber])) {
            return $titles[$sessionNumber];
        }

        // Generic session titles
        if ($sessionNumber <= 2) {
            return "Introduction to {$unit->name}";
        } elseif ($sessionNumber >= 11) {
            return "Review and Assessment";
        } else {
            return "Week {$sessionNumber}: Core Concepts";
        }
    }

    private function generateSessionDescription(Unit $unit, int $sessionNumber): string
    {
        return "Week {$sessionNumber} session covering key concepts in {$unit->name}. Students will engage in theoretical learning and practical exercises.";
    }

    private function getSessionType(int $sessionNumber): string
    {
        if ($sessionNumber === 1) {
            return 'lecture'; // First session is a lecture introducing the subject
        } elseif ($sessionNumber >= 11) {
            return 'review';
        } elseif ($sessionNumber % 6 === 0) {
            return 'assessment'; // Every 6th week has assessment
        } elseif ($sessionNumber % 3 === 0) {
            return 'practical'; // Every 3rd week has practical
        } else {
            return 'lecture';
        }
    }

    private function getPreparationNotes(Unit $unit, int $sessionNumber): ?string
    {
        if ($sessionNumber === 1) {
            return "Please review the unit outline and ensure you have access to required software/materials.";
        }

        return "Review previous session materials and complete assigned readings.";
    }

    private function getLearningOutcomes(Unit $unit, int $sessionNumber): ?string
    {
        return "By the end of this session, students will understand key concepts related to week {$sessionNumber} content.";
    }

    private function getLearningObjectives(Unit $unit, int $sessionNumber): array
    {
        return [
            "Understand week {$sessionNumber} concepts in {$unit->name}",
            "Apply theoretical knowledge to practical exercises",
            "Demonstrate understanding through participation and activities"
        ];
    }

    private function getTopicsCovered(Unit $unit, int $sessionNumber): array
    {
        $topics = [
            'COS10009' => [
                1 => ['Programming concepts', 'Development environment'],
                2 => ['Variables', 'Data types', 'Input/Output'],
                3 => ['Conditional statements', 'Boolean logic'],
                4 => ['Loops', 'Iteration'],
                5 => ['Functions', 'Parameters', 'Return values'],
            ],
            'COS10011' => [
                1 => ['Web technologies overview', 'Development tools'],
                2 => ['HTML structure', 'HTML elements'],
                3 => ['CSS basics', 'Styling'],
                4 => ['JavaScript fundamentals'],
                5 => ['DOM manipulation'],
            ],
        ];

        $unitTopics = $topics[$unit->code] ?? [];
        return $unitTopics[$sessionNumber] ?? ["Week {$sessionNumber} topics"];
    }

    private function isAssessmentSession(int $sessionNumber): bool
    {
        // Assessment sessions are typically week 6 (mid-term) and week 12 (final)
        return in_array($sessionNumber, [6, 12]);
    }

    private function getStudentInstructions(Unit $unit, int $sessionNumber): ?string
    {
        if ($sessionNumber === 1) {
            return "Welcome to {$unit->name}! Please bring required materials and be prepared to participate actively.";
        }

        if ($this->isAssessmentSession($sessionNumber)) {
            return "This is an assessment session. Please arrive on time and bring required materials.";
        }

        return "Come prepared with completed readings and any questions from previous sessions.";
    }

    private function getSessionMaterials(Unit $unit, int $sessionNumber): array
    {
        return [
            'slides' => "Week{$sessionNumber}_Slides.pdf",
            'readings' => "Chapter {$sessionNumber} - {$unit->name}",
            'exercises' => "Week{$sessionNumber}_Exercises.pdf",
        ];
    }

    private function hasHomework(int $sessionNumber): bool
    {
        // Most sessions have homework except first and last
        return $sessionNumber > 1 && $sessionNumber < 12;
    }

    private function getHomeworkDescription(Unit $unit, int $sessionNumber): ?string
    {
        if (!$this->hasHomework($sessionNumber)) {
            return null;
        }

        return "Complete exercises related to week {$sessionNumber} concepts. Submit via learning management system.";
    }

    private function getHomeworkDueDate(Carbon $sessionDate): ?string
    {
        return $sessionDate->copy()->addWeek()->toDateString();
    }

    private function getValidStartTime(CourseOffering $courseOffering): string
    {
        $startTime = $this->parseTimeFromSchedule($courseOffering->schedule_time_start, true);

        // If the parsed time looks invalid (like current date), use default
        if (strpos($startTime, '2025-') !== false) {
            return '10:00:00';
        }

        return $startTime;
    }

    private function getValidEndTime(CourseOffering $courseOffering): string
    {
        $startTime = $this->getValidStartTime($courseOffering);
        $endTime = $this->parseTimeFromSchedule($courseOffering->schedule_time_end, false);

        // If the parsed time looks invalid (like current date), use default
        if (strpos($endTime, '2025-') !== false) {
            $endTime = '12:00:00';
        }

        // Ensure end time is after start time
        $startCarbon = Carbon::createFromFormat('H:i:s', $startTime);
        $endCarbon = Carbon::createFromFormat('H:i:s', $endTime);

        if ($endCarbon->lte($startCarbon)) {
            // If end time is not after start time, add 2 hours to start time
            $endCarbon = $startCarbon->copy()->addHours(2);
        }

        return $endCarbon->format('H:i:s');
    }

    private function parseTimeFromSchedule($timeField, bool $isStartTime): string
    {
        if (is_null($timeField)) {
            return $isStartTime ? '10:00:00' : '12:00:00'; // Default times
        }

        // If it's already a Carbon object, extract time
        if ($timeField instanceof Carbon) {
            return $timeField->format('H:i:s');
        }

        // If it's already a time format string, return it
        if (is_string($timeField) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeField)) {
            return $timeField;
        }

        // If it's a datetime string, extract just the time part
        try {
            $carbon = Carbon::parse($timeField);
            return $carbon->format('H:i:s');
        } catch (\Exception $e) {
            // Fallback to default times ensuring start < end
            return $isStartTime ? '10:00:00' : '12:00:00';
        }
    }

    private function isHolidayPeriod(Carbon $date): bool
    {
        // Define holiday periods for FALL2024
        $holidays = [
            // Mid-semester break (example dates)
            ['start' => Carbon::create(2024, 9, 23), 'end' => Carbon::create(2024, 9, 27)],
            // Public holidays
            ['start' => Carbon::create(2024, 10, 14), 'end' => Carbon::create(2024, 10, 14)], // Example holiday
        ];

        foreach ($holidays as $holiday) {
            if ($date->between($holiday['start'], $holiday['end'])) {
                return true;
            }
        }

        return false;
    }
}
