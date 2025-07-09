<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Semester;
use App\Models\Unit;
use App\Models\Lecture;
use App\Models\CourseOffering;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Fall2024OfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates course offerings for FALL2024 semester
     */
    public function run(): void
    {
        $this->command->info('📅 Creating FALL2024 course offerings...');

        // Get FALL2024 semester
        $semester = Semester::where('code', 'FALL2024')->first();

        if (!$semester) {
            throw new \Exception('FALL2024 semester not found. Please create semester first.');
        }

        // Clean existing offerings and related data for this semester
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // For simplicity, truncate all related tables since this is seeding
        DB::table('assessment_component_details')->truncate();
        DB::table('assessment_components')->truncate();
        DB::table('syllabus')->truncate();
        DB::table('course_offerings')->where('semester_id', $semester->id)->delete();
        DB::table('course_registrations')->where('semester_id', $semester->id)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $units = Unit::all();
        $lecturers = Lecture::where('is_active', true)->get();

        if ($units->isEmpty() || $lecturers->isEmpty()) {
            throw new \Exception('Units or lecturers not found. Please run InitialSetup seeders first.');
        }

        // Select foundation units for first semester (Year 1, Semester 1)
        $foundationUnits = $this->getFoundationUnits($units);

        $offeringCount = 0;
        $syllabusCount = 0;

        // Group offerings by unit to track syllabus creation
        $unitsWithOfferings = collect();

        foreach ($foundationUnits as $unit) {
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
                    'current_enrollment' => 0, // Will be updated during registration
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
                    'notes' => "FALL2024 offering for first-year students",
                ]);

                $offeringCount++;

                // Track units that have offerings
                if (!$unitsWithOfferings->contains($unit->id)) {
                    $unitsWithOfferings->push($unit->id);
                }

                $this->command->info("  📖 {$unit->code} - {$lecturer->first_name} {$lecturer->last_name}" .
                    ($courseOffering->section_code ? " (Section {$courseOffering->section_code})" : ""));
            }
        }

        // Create syllabi for all units (one per unit per semester)
        foreach ($foundationUnits->whereIn('id', $unitsWithOfferings) as $unit) {
            $this->createSyllabusForUnit($unit, $semester);
            $syllabusCount++;
        }

        $this->command->info("✅ Created {$offeringCount} course offerings for FALL2024!");
        $this->command->info("✅ Created {$syllabusCount} syllabi for FALL2024!");
    }

    private function getFoundationUnits($units)
    {
        // Foundation units for first semester (Year 1, Semester 1)
        $foundationCodes = [
            'COS10009', // Introduction to Programming
            'COS10011', // Creating Web Applications
            'MAT10001', // Mathematics for Computing
            'ENG10001', // English for Academic Purposes
            'HRM10001', // Introduction to Human Resource Management
            'MKT10001', // Introduction to Marketing
            'ACC10007', // Accounting for Decision Making
            'ENG10002', // Engineering Fundamentals
            'ENG10003', // Engineering Mathematics
        ];

        return $units->whereIn('code', $foundationCodes);
    }

    private function getSectionCount(Unit $unit): int
    {
        // Popular foundation units get multiple sections
        $popularUnits = ['COS10009', 'COS10011', 'MKT10001', 'ACC10007', 'ENG10001', 'ENG10002'];

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

        // Fallback to any available lecturer
        return $lecturers->random();
    }

    private function getCapacityForUnit(Unit $unit): int
    {
        // Foundation units have higher capacity for first-year students
        return rand(40, 60);
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
        $roomNumbers = ['101', '102', '201', '202', '301', '302', 'Lab A', 'Lab B', 'Auditorium'];

        return "Room " . $roomNumbers[array_rand($roomNumbers)] . ", " . $campusName;
    }

    private function createSyllabusForUnit(Unit $unit, Semester $semester): void
    {
        // Check if syllabus already exists for this unit and semester
        $existingSyllabus = Syllabus::where('unit_id', $unit->id)
            ->where('semester_id', $semester->id)
            ->where('is_active', true)
            ->first();

        if ($existingSyllabus) {
            return; // Syllabus already exists
        }

        // Create syllabus
        $syllabus = Syllabus::create([
            'unit_id' => $unit->id,
            'version' => 'v1.0',
            'description' => $this->generateSyllabusDescription($unit),
            'total_hours' => $this->getTotalHours($unit),
            'hours_per_session' => $this->getHoursPerSession($unit),
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);

        // Create assessment components
        $this->createAssessmentComponents($syllabus, $unit);
    }

    private function generateSyllabusDescription(Unit $unit): string
    {
        $descriptions = [
            'COS10009' => "Introduction to Programming provides students with fundamental programming concepts using modern programming languages. Students will learn problem-solving techniques, algorithm design, and software development practices.",
            'COS10011' => "Creating Web Applications introduces students to web development technologies including HTML, CSS, JavaScript, and server-side programming. Students will build dynamic web applications.",
            'MAT10001' => "Mathematics for Computing covers essential mathematical concepts for computer science including discrete mathematics, logic, and statistical foundations.",
            'ENG10001' => "English for Academic Purposes develops academic writing and communication skills essential for university study and professional practice.",
            'HRM10001' => "Introduction to Human Resource Management explores the fundamental principles of managing people in organizations, including recruitment, performance management, and employee development.",
            'MKT10001' => "Introduction to Marketing examines marketing concepts, consumer behavior, market research, and marketing strategy development in contemporary business environments.",
            'ACC10007' => "Accounting for Decision Making provides fundamental accounting principles and practices for business decision-making, including financial reporting and analysis.",
            'ENG10002' => "Engineering Fundamentals introduces core engineering principles, problem-solving methodologies, and professional engineering practices.",
            'ENG10003' => "Engineering Mathematics covers mathematical foundations essential for engineering disciplines including calculus, linear algebra, and differential equations.",
        ];

        return $descriptions[$unit->code] ?? "This unit provides comprehensive coverage of {$unit->name} with theoretical foundations and practical applications.";
    }

    private function getTotalHours(Unit $unit): int
    {
        // Calculate based on credit points (typically 10 hours per credit point)
        return (int)($unit->credit_points * 10);
    }

    private function getHoursPerSession(Unit $unit): int
    {
        // Most foundation units have 2-3 hour sessions
        return rand(2, 3);
    }

    private function createAssessmentComponents(Syllabus $syllabus, Unit $unit): void
    {
        $unitCode = $unit->code;
        $assessmentStructure = $this->getFoundationAssessmentStructure($unitCode);

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

    private function getFoundationAssessmentStructure(string $unitCode): array
    {
        // Define assessment structures for foundation units
        $structures = [
            'COS10009' => [ // Introduction to Programming
                ['name' => 'Weekly Programming Exercises', 'weight' => 20.00, 'type' => 'assignment', 'required_for_final' => false],
                [
                    'name' => 'Programming Assignments',
                    'weight' => 40.00,
                    'type' => 'assignment',
                    'required_for_final' => true,
                    'details' => [
                        ['name' => 'Assignment 1: Basic Programming', 'weight' => 15.00],
                        ['name' => 'Assignment 2: Data Structures', 'weight' => 25.00],
                    ]
                ],
                ['name' => 'Mid-term Exam', 'weight' => 15.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'COS10011' => [ // Creating Web Applications
                [
                    'name' => 'Web Development Assignments',
                    'weight' => 50.00,
                    'type' => 'assignment',
                    'required_for_final' => true,
                    'details' => [
                        ['name' => 'HTML/CSS Assignment', 'weight' => 15.00],
                        ['name' => 'JavaScript Assignment', 'weight' => 15.00],
                        ['name' => 'Full Stack Project', 'weight' => 20.00],
                    ]
                ],
                ['name' => 'Portfolio Project', 'weight' => 30.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 20.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'MAT10001' => [ // Mathematics for Computing
                ['name' => 'Problem Sets', 'weight' => 25.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Mid-term Test', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
                ['name' => 'Final Exam', 'weight' => 50.00, 'type' => 'exam', 'required_for_final' => true],
            ],
            'ENG10001' => [ // English for Academic Purposes
                ['name' => 'Essay Assignments', 'weight' => 40.00, 'type' => 'assignment', 'required_for_final' => true],
                ['name' => 'Presentation', 'weight' => 20.00, 'type' => 'other', 'required_for_final' => false],
                ['name' => 'Research Project', 'weight' => 25.00, 'type' => 'project', 'required_for_final' => true],
                ['name' => 'Final Portfolio', 'weight' => 15.00, 'type' => 'assignment', 'required_for_final' => true],
            ],
        ];

        // Default structure for business and engineering units
        $defaultStructure = [
            ['name' => 'Assignments', 'weight' => 30.00, 'type' => 'assignment', 'required_for_final' => true],
            ['name' => 'Mid-term Exam', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
            ['name' => 'Group Project', 'weight' => 20.00, 'type' => 'project', 'required_for_final' => true],
            ['name' => 'Final Exam', 'weight' => 25.00, 'type' => 'exam', 'required_for_final' => true],
        ];

        return $structures[$unitCode] ?? $defaultStructure;
    }
}
