<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\Lecture;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;

class CourseOfferingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates course offerings based on existing curriculum units with appropriate semester numbers.
     */
    public function run(): void
    {
        $this->command->info('📚 Creating course offerings based on curriculum units...');

        // Verify lecturer exists
        $lecturer = Lecture::find(1);
        if (!$lecturer) {
            throw new \Exception('Lecturer with ID 1 not found. Please ensure lecturer exists before running this seeder.');
        }
        // Verify campus exists
        $campus = Campus::where('code', 'HN')->first();
        if (!$campus) {
            throw new \Exception('Campus with code HN not found. Please ensure campus exists before running this seeder.');
        }

        // Get the current active semester
        $activeSemester = Semester::where('is_active', true)->first();
        if (!$activeSemester) {
            throw new \Exception('No active semester found.');
        }

        $this->command->info("Creating offerings for active semester: {$activeSemester->code}");

        // Get curriculum units based on enrollments for the active semester
        $enrollments = Enrollment::with('student.curriculumVersion')
            ->where('semester_id', $activeSemester->id)
            ->where('status', 'in_progress')
            ->get();

        if ($enrollments->isEmpty()) {
            throw new \Exception('No enrollments found for active semester.');
        }

        // Get unique curriculum units from enrollments
        $curriculumUnitIds = collect();
        foreach ($enrollments as $enrollment) {
            $versionId = $enrollment->student->curriculum_version_id;
            $unitIds = CurriculumUnit::where('curriculum_version_id', $versionId)
                ->where('semester_number', $enrollment->semester_number)
                ->pluck('id');
            $curriculumUnitIds = $curriculumUnitIds->merge($unitIds);
        }

        // Get unique curriculum units with their relationships
        $curriculumUnits = CurriculumUnit::with('unit')
            ->whereIn('id', $curriculumUnitIds->unique())
            ->get();

        $offeringCount = 0;
        $skippedCount = 0;

        foreach ($curriculumUnits as $curriculumUnit) {
            // Check if course offering already exists
            $existingOffering = CourseOffering::where('semester_id', $activeSemester->id)
                ->where('curriculum_unit_id', $curriculumUnit->id)
                ->first();

            if ($existingOffering) {
                $skippedCount++;
                continue;
            }

            // Generate unique section code for this curriculum unit
            $sectionCode = '01'; // Default to section 01
            $sectionNumber = 1;
            while (CourseOffering::where('semester_id', $activeSemester->id)
                ->where('curriculum_unit_id', $curriculumUnit->id)
                ->where('section_code', sprintf('%02d', $sectionNumber))
                ->exists()
            ) {
                $sectionNumber++;
            }
            $sectionCode = sprintf('%02d', $sectionNumber);
            $timeSlot = $this->getTimeSlot();
            // Create course offering
            CourseOffering::create([
                'semester_id' => $activeSemester->id,
                'curriculum_unit_id' => $curriculumUnit->id,
                'lecture_id' => 1,
                'campus_id' => 1,
                'section_code' => $sectionCode,
                'max_capacity' => 120,
                'current_enrollment' => 0,
                'waitlist_capacity' => 5,
                'current_waitlist' => 0,
                'delivery_mode' => 'in_person',
                'schedule_days' => $this->getScheduleDays(),
                'schedule_time_start' => $timeSlot['start'],
                'schedule_time_end' => $timeSlot['end'],
                'location' => $this->getLocation(),
                'is_active' => true,
                'enrollment_status' => 'open',
                'notes' => 'Generated course offering for ' . $curriculumUnit->unit->code . ' - Section ' . $sectionCode
            ]);

            $offeringCount++;
        }

        $this->command->info("✅ Course offerings creation completed!");
        $this->command->info("📊 Created: {$offeringCount} new offerings");
        $this->command->info("⏭️  Skipped: {$skippedCount} existing offerings");
    }

    /**
     * Get capacity based on unit characteristics
     */
    private function getCapacityForUnit(CurriculumUnit $curriculumUnit): int
    {
        $unit = $curriculumUnit->unit;

        // Base capacity on unit type and level
        if (!$unit) {
            return 40; // Default capacity
        }

        // Higher capacity for introductory units
        if (str_contains($unit->code, '10')) {
            return rand(45, 60); // First year units get larger capacity
        }

        // Lower capacity for advanced units
        if (str_contains($unit->code, '30') || str_contains($unit->code, '40')) {
            return rand(25, 35); // Advanced units get smaller capacity
        }

        // Standard capacity for second year units
        return rand(35, 45);
    }

    /**
     * Get delivery mode (mix of in-person, online, hybrid)
     */
    private function getDeliveryMode(): string
    {
        $modes = ['in_person', 'online', 'hybrid'];
        $weights = [60, 25, 15]; // 60% in-person, 25% online, 15% hybrid

        $rand = rand(1, 100);
        if ($rand <= $weights[0]) {
            return $modes[0];
        } elseif ($rand <= $weights[0] + $weights[1]) {
            return $modes[1];
        } else {
            return $modes[2];
        }
    }

    /**
     * Generate realistic schedule days
     */
    private function getScheduleDays(): array
    {
        $dayOptions = [
            ['Monday', 'Wednesday'],
            ['Tuesday', 'Thursday'],
            ['Monday', 'Wednesday', 'Friday'],
            ['Tuesday', 'Thursday'],
            ['Monday'],
            ['Tuesday'],
            ['Wednesday'],
            ['Thursday'],
            ['Friday'],
        ];

        return $dayOptions[2];
    }

    /**
     * Generate time slots
     */
    private function getTimeSlot(): array
    {
        $timeSlots = [
            ['start' => '08:00:00', 'end' => '10:00:00'],
            ['start' => '10:00:00', 'end' => '12:00:00'],
            ['start' => '13:00:00', 'end' => '15:00:00'],
            ['start' => '15:00:00', 'end' => '17:00:00'],
            ['start' => '17:00:00', 'end' => '19:00:00'],
        ];

        return $timeSlots[1];
    }

    /**
     * Generate location
     */
    private function getLocation(): string
    {
        $buildings = ['Building A', 'Building B', 'Building C', 'Engineering Building', 'Business Building'];
        $roomTypes = ['Room', 'Lab', 'Lecture Hall', 'Tutorial Room'];
        $roomNumbers = ['101', '102', '103', '201', '202', '203', '301', '302', 'A01', 'B02', 'C03'];

        $building = $buildings[array_rand($buildings)];
        $roomType = $roomTypes[array_rand($roomTypes)];
        $roomNumber = $roomNumbers[array_rand($roomNumbers)];

        return "{$roomType} {$roomNumber}, {$building}";
    }

    /**
     * Generate notes for the course offering
     */
    private function generateNotes(CurriculumUnit $curriculumUnit, Semester $semester): string
    {
        $unit = $curriculumUnit->unit;
        $unitCode = $unit?->code ?? 'Unknown';
        $unitName = $unit?->name ?? 'Unknown Unit';
        $semesterNumber = $curriculumUnit->semester_number;

        return "Course offering for {$unitCode} - {$unitName} in {$semester->code}. " .
            "This is a semester {$semesterNumber} unit with one section available.";
    }
}
