<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Semester;
use App\Models\Unit;
use App\Models\Lecture;
use App\Models\CourseOffering;
use Illuminate\Database\Seeder;

class SemesterAndOfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates course offerings for the current semester and assigns lecturers
     */
    public function run(): void
    {
        $this->command->info('📅 Creating course offerings...');

        // Clean existing data
        $this->cleanExistingData();

        // Create course offerings for current semester
        $this->createCourseOfferings();

        $this->command->info('✅ Course offerings created successfully!');
    }

    private function cleanExistingData(): void
    {
        CourseOffering::query()->delete();
    }

    private function createCourseOfferings(): void
    {
        // Create semesters if they don't exist
        $currentSemester = Semester::firstOrCreate(
            ['code' => 'SPRING2025'],
            [
                'name' => 'Spring Semester 2025',
                'start_date' => '2025-02-01',
                'end_date' => '2025-05-31',
                'enrollment_start_date' => '2025-01-15',
                'enrollment_end_date' => '2025-02-15',
                'is_active' => true,
                'is_archived' => false,
            ]
        );

        $futureSemester = Semester::firstOrCreate(
            ['code' => 'SUMMER2025'],
            [
                'name' => 'Summer Semester 2025',
                'start_date' => '2025-06-01',
                'end_date' => '2025-08-31',
                'enrollment_start_date' => '2025-05-15',
                'enrollment_end_date' => '2025-06-15',
                'is_active' => false,
                'is_archived' => false,
            ]
        );

        $units = Unit::all();
        $lecturers = Lecture::where('is_active', true)->get();

        if ($units->isEmpty() || $lecturers->isEmpty()) {
            throw new \Exception('Units or lecturers not found. Please run previous seeders first.');
        }

        // Create offerings for current semester (Spring 2025)
        $this->createOfferingsForSemester($currentSemester, $units, $lecturers, 'current');

        // Create offerings for future semester (Summer 2025)
        $this->createOfferingsForSemester($futureSemester, $units, $lecturers, 'future');
    }

    private function createOfferingsForSemester(Semester $semester, $units, $lecturers, string $semesterType): void
    {
        $this->command->info("📚 Creating offerings for {$semester->name}...");

        // Select a subset of units for each semester (not all units are offered every semester)
        $selectedUnits = $semesterType === 'current'
            ? $units->take(25) // Offer 25 units in current semester
            : $units->skip(15)->take(20); // Offer different 20 units in future semester

        foreach ($selectedUnits as $unit) {
            // Determine how many sections this unit should have
            $sectionCount = $this->getSectionCount($unit);

            for ($i = 1; $i <= $sectionCount; $i++) {
                // Assign a lecturer based on their specialization
                $lecturer = $this->assignLecturerToUnit($unit, $lecturers);

                $courseOffering = CourseOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $unit->id,
                    'lecture_id' => $lecturer->id,
                    'section_code' => $sectionCount > 1 ? sprintf('%02d', $i) : null,
                    'max_capacity' => $this->getCapacityForUnit($unit),
                    'current_enrollment' => $semesterType === 'current' ? rand(15, 35) : 0,
                    'waitlist_capacity' => 10,
                    'current_waitlist' => 0,
                    'delivery_mode' => $this->getDeliveryMode($lecturer),
                    'schedule_days' => $this->getScheduleDays(),
                    'schedule_time_start' => $this->getTimeSlot()['start'],
                    'schedule_time_end' => $this->getTimeSlot()['end'],
                    'location' => $this->getLocation($lecturer),
                    'is_active' => true,
                    'enrollment_status' => $semesterType === 'current' ? 'open' : 'open',
                    'registration_start_date' => $semester->enrollment_start_date?->toDateString(),
                    'registration_end_date' => $semester->enrollment_end_date?->toDateString(),
                    'notes' => "Created by seeder for {$semester->name}",
                ]);

                $this->command->info("  📖 {$unit->code} - {$lecturer->first_name} {$lecturer->last_name}" .
                    ($courseOffering->section_code ? " (Section {$courseOffering->section_code})" : ""));
            }
        }
    }

    private function getSectionCount(Unit $unit): int
    {
        // Foundation and popular units get multiple sections
        $popularUnits = ['COS10009', 'COS10011', 'COS20007', 'MKT10001', 'ACC10007', 'ENG10001', 'ENG10002'];

        if (in_array($unit->code, $popularUnits)) {
            return rand(2, 3); // 2-3 sections for popular units
        }

        return 1; // Most units have 1 section
    }

    private function assignLecturerToUnit(Unit $unit, $lecturers)
    {
        // Try to match lecturer specialization with unit
        $unitCode = $unit->code;

        // Define unit-to-department mapping
        $departmentMapping = [
            'COS' => 'Computer Science',
            'TNE' => 'Computer Science',
            'MKT' => 'Business',
            'ACC' => 'Business',
            'HRM' => 'Business',
            'ECO' => 'Business',
            'FIN' => 'Business',
            'ENG' => 'Engineering',
            'CIV' => 'Engineering',
            'MEC' => 'Engineering',
            'ELE' => 'Engineering',
            'MAT' => 'Mathematics',
            'ENG' => 'English',
        ];

        $unitPrefix = substr($unitCode, 0, 3);
        $targetDepartment = $departmentMapping[$unitPrefix] ?? null;

        if ($targetDepartment) {
            $matchingLecturers = $lecturers->where('department', $targetDepartment);
            if ($matchingLecturers->isNotEmpty()) {
                return $matchingLecturers->random();
            }
        }

        // Fallback to any available lecturer
        return $lecturers->random();
    }

    private function getCapacityForUnit(Unit $unit): int
    {
        // Foundation units have higher capacity
        $foundationUnits = ['COS10009', 'COS10011', 'MKT10001', 'ACC10007', 'ENG10001'];

        if (in_array($unit->code, $foundationUnits)) {
            return rand(40, 60);
        }

        // Advanced units have lower capacity
        if (str_contains($unit->code, '40') || str_contains($unit->code, '30')) {
            return rand(20, 30);
        }

        return rand(25, 40);
    }

    private function getDeliveryMode(Lecture $lecturer): string
    {
        $modes = ['in_person', 'online', 'hybrid'];

        // Lecturers who can teach online are more likely to have online/hybrid modes
        if ($lecturer->can_teach_online) {
            return $modes[array_rand($modes)];
        }

        return 'in_person';
    }

    private function getScheduleDays(): array
    {
        $dayOptions = [
            ['Monday', 'Wednesday'],
            ['Tuesday', 'Thursday'],
            ['Monday', 'Wednesday', 'Friday'],
            ['Tuesday', 'Thursday'],
            ['Friday'],
            ['Saturday'],
        ];

        return $dayOptions[array_rand($dayOptions)];
    }

    private function getTimeSlot(): array
    {
        $timeSlots = [
            ['start' => '08:00:00', 'end' => '10:00:00'],
            ['start' => '10:00:00', 'end' => '12:00:00'],
            ['start' => '13:00:00', 'end' => '15:00:00'],
            ['start' => '15:00:00', 'end' => '17:00:00'],
            ['start' => '17:00:00', 'end' => '19:00:00'],
            ['start' => '19:00:00', 'end' => '21:00:00'],
        ];

        return $timeSlots[array_rand($timeSlots)];
    }

    private function getLocation(Lecture $lecturer): string
    {
        $campusName = $lecturer->campus->name ?? 'Main Campus';
        $roomNumbers = ['101', '102', '201', '202', '301', '302', 'Lab A', 'Lab B', 'Auditorium'];

        return "Room " . $roomNumbers[array_rand($roomNumbers)] . ", " . $campusName;
    }
}
