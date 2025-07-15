<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Semester;
use App\Models\Unit;
use App\Models\Lecture;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use Illuminate\Database\Seeder;

class Spring2025CourseOfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates course offerings for SPRING2025 semester
     */
    public function run(): void
    {
        $this->command->info('📅 Creating SPRING2025 course offerings...');

        // Get SPRING2025 semester
        $semester = Semester::where('code', 'SPRING2025')->first();

        if (!$semester) {
            throw new \Exception('SPRING2025 semester not found. Please create semester first.');
        }

        // Check if offerings already exist for this semester
        $existingOfferings = CourseOffering::where('semester_id', $semester->id)->count();
        if ($existingOfferings > 0) {
            $this->command->info("✅ Course offerings already exist for SPRING2025 ({$existingOfferings} found). Skipping creation.");
            return;
        }

        $units = Unit::all();
        $lecturers = Lecture::where('is_active', true)->get();

        if ($units->isEmpty() || $lecturers->isEmpty()) {
            throw new \Exception('Units or lecturers not found. Please run InitialSetup seeders first.');
        }

        // Get second semester units (Year 1, Semester 2)
        $secondSemesterUnits = $this->getSecondSemesterUnits($units);

        // Also offer some retake opportunities for failed first semester units
        $retakeUnits = $this->getRetakeUnits($units);

        $offeringCount = 0;
        $syllabusCount = 0;
        $unitsWithOfferings = collect();

        // Create offerings for second semester units
        foreach ($secondSemesterUnits as $unit) {
            $sectionCount = $this->getSectionCount($unit);

            for ($i = 1; $i <= $sectionCount; $i++) {
                $lecturer = $this->assignLecturerToUnit($unit, $lecturers);

                $courseOffering = CourseOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $unit->id,
                    'lecture_id' => $lecturer->id,
                    'section_code' => $sectionCount > 1 ? sprintf('%02d', $i) : null,
                    'max_capacity' => $this->getCapacityForUnit($unit),
                    'current_enrollment' => 0,
                    'waitlist_capacity' => 10,
                    'current_waitlist' => 0,
                    'delivery_mode' => $this->getDeliveryMode($lecturer),
                    'schedule_days' => $this->getScheduleDays(),
                    'schedule_time_start' => $this->getTimeSlot()['start'],
                    'schedule_time_end' => $this->getTimeSlot()['end'],
                    'location' => $this->getLocation($lecturer),
                    'is_active' => true,
                    'enrollment_status' => 'open',
                    'registration_start_date' => $semester->enrollment_start_date?->toDateString(),
                    'registration_end_date' => $semester->enrollment_end_date?->toDateString(),
                    'notes' => "SPRING2025 offering for second semester students",
                ]);

                $offeringCount++;

                // Track units that have offerings
                if (!$unitsWithOfferings->contains($unit->id)) {
                    $unitsWithOfferings->push($unit->id);
                }
            }
        }

        // Create retake offerings (limited sections)
        foreach ($retakeUnits as $unit) {
            $lecturer = $this->assignLecturerToUnit($unit, $lecturers);

            $courseOffering = CourseOffering::create([
                'semester_id' => $semester->id,
                'unit_id' => $unit->id,
                'lecture_id' => $lecturer->id,
                'section_code' => 'R01', // Retake section
                'max_capacity' => 25, // Smaller capacity for retakes
                'current_enrollment' => 0,
                'waitlist_capacity' => 5,
                'current_waitlist' => 0,
                'delivery_mode' => $this->getDeliveryMode($lecturer),
                'schedule_days' => $this->getScheduleDays(),
                'schedule_time_start' => $this->getTimeSlot()['start'],
                'schedule_time_end' => $this->getTimeSlot()['end'],
                'location' => $this->getLocation($lecturer),
                'is_active' => true,
                'enrollment_status' => 'open',
                'registration_start_date' => $semester->enrollment_start_date?->toDateString(),
                'registration_end_date' => $semester->enrollment_end_date?->toDateString(),
                'notes' => "SPRING2025 retake offering for students who failed in FALL2024",
            ]);

            $offeringCount++;

            // Track retake units
            if (!$unitsWithOfferings->contains($unit->id)) {
                $unitsWithOfferings->push($unit->id);
            }
        }

        // Create syllabi for all units that have offerings
        $allUnitsWithOfferings = $units->whereIn('id', $unitsWithOfferings);
        foreach ($allUnitsWithOfferings as $unit) {
            $this->createSyllabusForUnit($unit, $semester);
            $syllabusCount++;
        }

        $this->command->info("✅ Created {$offeringCount} course offerings for SPRING2025!");
        $this->command->info("✅ Created {$syllabusCount} syllabi for SPRING2025!");
    }

    private function getSecondSemesterUnits($units)
    {
        // Second semester units for first-year students
        $secondSemesterCodes = [
            'COS10026', // Computing Technology Inquiry Project
            'COS10020', // Introduction to Database Systems
            'MAT10002', // Statistics for Computing
            'ENG10004', // Technical Communication
            'HRM10002', // Organizational Behavior
            'MKT10002', // Consumer Behavior
            'ACC10008', // Management Accounting
            'ENG10005', // Engineering Design
            'ENG10006', // Applied Engineering Mathematics
        ];

        return $units->whereIn('code', $secondSemesterCodes);
    }

    private function getRetakeUnits($units)
    {
        // Common units that students might need to retake
        $retakeCodes = [
            'COS10009', // Introduction to Programming
            'MAT10001', // Mathematics for Computing
            'ENG10001', // English for Academic Purposes
            'ACC10007', // Accounting for Decision Making
        ];

        return $units->whereIn('code', $retakeCodes);
    }

    private function getSectionCount(Unit $unit): int
    {
        // Popular second semester units get multiple sections (fixed count to avoid duplicates)
        $popularUnits = [
            'COS10026' => 3, // Computing Technology Inquiry Project
            'COS10020' => 2, // Introduction to Database Systems
            'MAT10002' => 2, // Statistics for Computing
            'ENG10004' => 2, // Technical Communication
        ];

        return $popularUnits[$unit->code] ?? 1;
    }

    private function assignLecturerToUnit(Unit $unit, $lecturers)
    {
        $unitCode = $unit->code;

        // Define unit-to-department mapping
        $departmentMapping = [
            'COS' => 'Computer Science',
            'MKT' => 'Business',
            'ACC' => 'Business',
            'HRM' => 'Business',
            'ENG' => 'Engineering',
            'MAT' => 'Mathematics',
        ];

        $unitPrefix = substr($unitCode, 0, 3);
        $targetDepartment = $departmentMapping[$unitPrefix] ?? null;

        if ($targetDepartment) {
            $matchingLecturers = $lecturers->where('department', $targetDepartment);
            if ($matchingLecturers->isNotEmpty()) {
                return $matchingLecturers->random();
            }
        }

        return $lecturers->random();
    }

    private function getCapacityForUnit(Unit $unit): int
    {
        return rand(35, 55);
    }

    private function getDeliveryMode(Lecture $lecturer): string
    {
        $modes = ['in_person', 'online', 'hybrid'];

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
        ];

        return $timeSlots[array_rand($timeSlots)];
    }

    private function getLocation(Lecture $lecturer): string
    {
        $campusName = $lecturer->campus->name ?? 'Main Campus';
        $roomNumbers = ['103', '104', '203', 'Lab C', 'Lab D', 'Tutorial Room 1'];

        return "Room " . $roomNumbers[array_rand($roomNumbers)] . ", " . $campusName;
    }

    private function createSyllabusForUnit(Unit $unit, Semester $semester): void
    {
        // Find curriculum unit for this unit (simplified approach for test data)
        $curriculumUnit = \App\Models\CurriculumUnit::where('unit_id', $unit->id)->first();

        if (!$curriculumUnit) {
            // Skip creating syllabus if no curriculum unit found
            return;
        }

        // Check if syllabus already exists for this curriculum unit
        $existingSyllabus = Syllabus::where('curriculum_unit_id', $curriculumUnit->id)
            ->where('is_active', true)
            ->first();

        if ($existingSyllabus) {
            return; // Syllabus already exists
        }

        // Create syllabus
        $syllabus = Syllabus::create([
            'curriculum_unit_id' => $curriculumUnit->id,
            'version' => 'v1.0',
            'description' => $this->generateSyllabusDescription($unit),
            'total_hours' => $this->getTotalHours($unit),
            'hours_per_session' => $this->getHoursPerSession($unit),
            'is_active' => true,
        ]);

        // Create assessment components
        $this->createAssessmentComponents($syllabus, $unit);
    }

    private function generateSyllabusDescription(Unit $unit): string
    {
        // Extended descriptions for second semester units
        $descriptions = [
            'COS10003' => "Web Application Development builds upon programming fundamentals to create dynamic web applications using modern frameworks and databases.",
            'COS20007' => "Object Oriented Programming introduces advanced programming concepts including inheritance, polymorphism, and design patterns.",
            'MAT10002' => "Advanced Mathematics for Computing covers calculus, probability, and statistical analysis for computer science applications.",
            'ENG10004' => "Technical Communication develops professional communication skills for technical environments and project documentation.",
            'HRM10002' => "Organizational Behavior examines individual and group behavior in organizational settings and leadership principles.",
            'MKT10002' => "Consumer Behavior and Market Research explores consumer psychology and research methodologies in marketing.",
            'ACC10008' => "Management Accounting focuses on internal reporting, cost analysis, and decision-making for business operations.",
            'ENG10005' => "Engineering Design introduces systematic design processes and project management for engineering solutions.",
            'ENG10006' => "Materials and Manufacturing covers material properties and manufacturing processes in engineering applications.",
        ];

        return $descriptions[$unit->code] ?? "This unit advances knowledge in {$unit->name} with practical applications and advanced concepts.";
    }

    private function getTotalHours(Unit $unit): int
    {
        return (int)($unit->credit_points * 10);
    }

    private function getHoursPerSession(Unit $unit): int
    {
        return rand(2, 3);
    }

    private function createAssessmentComponents(Syllabus $syllabus, Unit $unit): void
    {
        $unitCode = $unit->code;
        $assessmentStructure = $this->getSecondSemesterAssessmentStructure($unitCode);

        foreach ($assessmentStructure as $componentData) {
            $component = AssessmentComponent::create([
                'syllabus_id' => $syllabus->id,
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['required_for_final'] ?? true,
            ]);

            // Create component details if specified
            if (isset($componentData['details'])) {
                foreach ($componentData['details'] as $detailData) {
                    AssessmentComponentDetail::create([
                        'component_id' => $component->id,
                        'name' => $detailData['name'],
                        'weight' => $detailData['weight'],
                    ]);
                }
            }
        }
    }

    private function getSecondSemesterAssessmentStructure(string $unitCode): array
    {
        // Assessment structures for second semester units
        $structures = [
            'COS10003' => [ // Web Application Development
                ['name' => 'Lab Exercises', 'weight' => 25.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Web Application Project', 'weight' => 45.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 30.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'COS20007' => [ // Object Oriented Programming
                ['name' => 'Programming Assignments', 'weight' => 50.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Mid-term Exam', 'weight' => 20.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 30.00, 'type' => 'exam', 'required_for_final' => true],
            ],
        ];

        // Default structure for second semester units
        $defaultStructure = [
            ['name' => 'Assignments & Projects', 'weight' => 40.00, 'type' => 'assignment', 'required_for_final' => true],
            ['name' => 'Mid-term Assessment', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
            ['name' => 'Final Exam', 'weight' => 35.00, 'type' => 'exam', 'required_for_final' => true],
        ];

        return $structures[$unitCode] ?? $defaultStructure;
    }
}
