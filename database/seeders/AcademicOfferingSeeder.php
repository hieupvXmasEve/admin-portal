<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicOfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        AssessmentComponentDetail::query()->delete();
        AssessmentComponent::query()->delete();
        Syllabus::query()->delete();
        CourseOffering::query()->delete();

        // Get required data
        $fallSemester = Semester::where('code', 'FALL2024')->first();
        $springSemester = Semester::where('code', 'SPRING2025')->first();
        $units = Unit::whereNotIn('code', ['CAPSTONE_PROJECT', 'INTERNSHIP', 'ADVANCED_RESEARCH'])->get();
        $lecturers = Lecture::active()->get();

        if (!$fallSemester || !$springSemester || $units->isEmpty() || $lecturers->isEmpty()) {
            throw new \Exception('Required data not found. Please run previous seeders first.');
        }

        // Create course offerings for FALL2024 (past semester)
        $this->createCourseOfferings($fallSemester, $units, $lecturers, 'past');

        // Create course offerings for SPRING2025 (current semester)
        $this->createCourseOfferings($springSemester, $units, $lecturers, 'current');

        $this->command->info('Created course offerings with syllabi for FALL2024 and SPRING2025');
    }

    private function createCourseOfferings(Semester $semester, $units, $lecturers, string $semesterType): void
    {
        $unitCount = $semesterType === 'past' ? 20 : 15; // More offerings in past semester
        $selectedUnits = $units->random($unitCount);

        foreach ($selectedUnits as $unit) {
            // Create 1-2 sections per unit
            $sectionCount = rand(1, 2);
            
            for ($i = 1; $i <= $sectionCount; $i++) {
                $lecturer = $lecturers->random();
                
                $courseOffering = CourseOffering::create([
                    'semester_id' => $semester->id,
                    'unit_id' => $unit->id,
                    'lecture_id' => $lecturer->id,
                    'section_code' => $sectionCount > 1 ? sprintf('%02d', $i) : null,
                    'max_capacity' => $this->getRandomCapacity(),
                    'current_enrollment' => $semesterType === 'past' ? $this->getRandomEnrollment() : 0,
                    'waitlist_capacity' => 10,
                    'current_waitlist' => 0,
                    'delivery_mode' => $this->getRandomDeliveryMode($lecturer),
                    'schedule_days' => $this->getRandomScheduleDays(),
                    'schedule_time_start' => $this->getRandomTimeSlot()['start'],
                    'schedule_time_end' => $this->getRandomTimeSlot()['end'],
                    'location' => $this->getRandomLocation(),
                    'is_active' => true,
                    'enrollment_status' => $semesterType === 'past' ? 'closed' : 'open',
                    'registration_start_date' => $semester->enrollment_start_date,
                    'registration_end_date' => $semester->enrollment_end_date,
                    'notes' => "Section {$i} of {$unit->code} for {$semester->name}",
                ]);

                // Create syllabus for this offering
                $this->createSyllabusForOffering($courseOffering);
            }
        }
    }

    private function createSyllabusForOffering(CourseOffering $courseOffering): void
    {
        $syllabus = Syllabus::create([
            'unit_id' => $courseOffering->unit_id,
            'version' => 'v1.0',
            'description' => "Course syllabus for {$courseOffering->unit->code} covering fundamental concepts and practical applications.",
            'total_hours' => rand(60, 150),
            'hours_per_session' => rand(2, 4),
            'semester_id' => $courseOffering->semester_id,
            'is_active' => true,
        ]);

        // Create assessment components
        $this->createAssessmentComponents($syllabus);
    }

    private function createAssessmentComponents(Syllabus $syllabus): void
    {
        // Define typical assessment structures
        $assessmentStructures = [
            [
                ['name' => 'Final Exam', 'type' => 'exam', 'weight' => 50],
                ['name' => 'Midterm Quiz', 'type' => 'quiz', 'weight' => 20],
                ['name' => 'Group Project', 'type' => 'project', 'weight' => 20],
                ['name' => 'Online Activities', 'type' => 'online_activity', 'weight' => 10],
            ],
            [
                ['name' => 'Final Exam', 'type' => 'exam', 'weight' => 40],
                ['name' => 'Assignment 1', 'type' => 'assignment', 'weight' => 15],
                ['name' => 'Assignment 2', 'type' => 'assignment', 'weight' => 15],
                ['name' => 'Project Work', 'type' => 'project', 'weight' => 25],
                ['name' => 'Class Participation', 'type' => 'other', 'weight' => 5],
            ],
            [
                ['name' => 'Portfolio', 'type' => 'project', 'weight' => 40],
                ['name' => 'Practical Exam', 'type' => 'exam', 'weight' => 30],
                ['name' => 'Weekly Quizzes', 'type' => 'quiz', 'weight' => 20],
                ['name' => 'Reflection Essays', 'type' => 'assignment', 'weight' => 10],
            ],
        ];

        $structure = $assessmentStructures[array_rand($assessmentStructures)];

        foreach ($structure as $componentData) {
            $component = AssessmentComponent::create([
                'syllabus_id' => $syllabus->id,
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['type'] !== 'other',
            ]);

            // Add details for some components
            if (in_array($componentData['type'], ['project', 'assignment']) && rand(0, 1)) {
                $this->createComponentDetails($component);
            }
        }
    }

    private function createComponentDetails(AssessmentComponent $component): void
    {
        $detailOptions = [
            'project' => [
                ['name' => 'Proposal', 'weight' => 20],
                ['name' => 'Implementation', 'weight' => 50],
                ['name' => 'Presentation', 'weight' => 30],
            ],
            'assignment' => [
                ['name' => 'Literature Review', 'weight' => 40],
                ['name' => 'Analysis', 'weight' => 35],
                ['name' => 'Conclusion', 'weight' => 25],
            ],
        ];

        $details = $detailOptions[$component->type] ?? [];

        foreach ($details as $detail) {
            AssessmentComponentDetail::create([
                'component_id' => $component->id,
                'name' => $detail['name'],
                'weight' => $detail['weight'],
            ]);
        }
    }

    private function getRandomCapacity(): int
    {
        return collect([30, 40, 50, 60, 80, 100])->random();
    }

    private function getRandomEnrollment(): int
    {
        // For past semesters, generate realistic enrollment numbers
        $capacity = $this->getRandomCapacity();
        return rand(min(25, $capacity), $capacity);
    }

    private function getRandomDeliveryMode(Lecture $lecturer): string
    {
        $modes = ['in_person'];
        
        if ($lecturer->can_teach_online) {
            $modes[] = 'online';
            $modes[] = 'hybrid';
        }
        
        return collect($modes)->random();
    }

    private function getRandomScheduleDays(): array
    {
        $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $dayCount = rand(1, 3);
        
        return collect($allDays)->random($dayCount)->values()->toArray();
    }

    private function getRandomTimeSlot(): array
    {
        $timeSlots = [
            ['start' => '08:00', 'end' => '10:00'],
            ['start' => '10:00', 'end' => '12:00'],
            ['start' => '12:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '18:00'],
            ['start' => '18:00', 'end' => '20:00'],
        ];
        
        return collect($timeSlots)->random();
    }

    private function getRandomLocation(): string
    {
        $buildings = ['Building A', 'Building B', 'Engineering Block', 'Computer Science Center'];
        $building = collect($buildings)->random();
        $room = rand(101, 599);
        
        return "{$building}, Room {$room}";
    }
}