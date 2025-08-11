<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MixedSemesterProgressionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Simulates students taking mixed semester loads (part-time, overload, etc.)
     */
    public function run(): void
    {
        $this->command->info('📊 Creating mixed semester progression scenarios...');

        // Get active students
        $students = Student::where('status', 'active')->get();

        if ($students->isEmpty()) {
            throw new \Exception('No active students found.');
        }

        // Get current and future semesters
        $currentSemester = Semester::where('code', 'SPRING2025')->first();
        $futureSemesters = Semester::where('start_date', '>', $currentSemester->end_date)
            ->orderBy('start_date')
            ->limit(3)
            ->get();

        if ($futureSemesters->isEmpty()) {
            $this->command->info('⚠️ No future semesters found. Creating sample future semesters...');
            $futureSemesters = $this->createSampleFutureSemesters();
        }

        $progressionCount = 0;

        foreach ($students->take(50) as $student) { // Process subset for demonstration
            $progressionType = $this->determineProgressionType($student);
            $this->createProgressionPlan($student, $currentSemester, $futureSemesters, $progressionType);
            $progressionCount++;
        }

        $this->command->info("✅ Created mixed progression plans for {$progressionCount} students!");
    }

    private function determineProgressionType(Student $student): array
    {
        // Get student's academic performance to influence progression type
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        $gpa = $latestGPA ? $latestGPA->gpa : 2.5;

        // Determine progression type based on various factors
        $progressionTypes = [
            [
                'type' => 'standard_fulltime',
                'probability' => 60,
                'description' => 'Standard full-time progression (4-5 units per semester)',
                'units_per_semester' => [4, 5],
                'suitable_for_gpa' => [0.0, 4.0],
            ],
            [
                'type' => 'part_time',
                'probability' => 15,
                'description' => 'Part-time study (2-3 units per semester)',
                'units_per_semester' => [2, 3],
                'suitable_for_gpa' => [0.0, 4.0],
            ],
            [
                'type' => 'overload',
                'probability' => 10,
                'description' => 'Overload progression (6+ units per semester)',
                'units_per_semester' => [6, 7],
                'suitable_for_gpa' => [3.0, 4.0], // Only high performers
            ],
            [
                'type' => 'reduced_load',
                'probability' => 10,
                'description' => 'Reduced load due to academic difficulties',
                'units_per_semester' => [2, 3],
                'suitable_for_gpa' => [0.0, 2.0], // Low performers
            ],
            [
                'type' => 'accelerated',
                'probability' => 5,
                'description' => 'Accelerated progression with summer units',
                'units_per_semester' => [5, 6],
                'suitable_for_gpa' => [3.5, 4.0], // Top performers only
            ],
        ];

        // Filter progression types based on GPA suitability
        $suitableTypes = array_filter($progressionTypes, function ($type) use ($gpa) {
            return $gpa >= $type['suitable_for_gpa'][0] && $gpa <= $type['suitable_for_gpa'][1];
        });

        // Select progression type based on probability
        $totalProbability = array_sum(array_column($suitableTypes, 'probability'));
        $random = rand(1, $totalProbability);
        $cumulative = 0;

        foreach ($suitableTypes as $type) {
            $cumulative += $type['probability'];
            if ($random <= $cumulative) {
                return $type;
            }
        }

        // Fallback to standard progression
        return $progressionTypes[0];
    }

    private function createProgressionPlan(Student $student, Semester $currentSemester, $futureSemesters, array $progressionType): void
    {
        // Update student notes with progression plan
        $this->updateStudentProgressionNotes($student, $progressionType);

        // Create future semester registrations based on progression type
        foreach ($futureSemesters as $semester) {
            $this->planSemesterRegistration($student, $semester, $progressionType);
        }
    }

    private function planSemesterRegistration(Student $student, Semester $semester, array $progressionType): void
    {
        $unitsToTake = rand($progressionType['units_per_semester'][0], $progressionType['units_per_semester'][1]);

        // Get available units for this student's level
        $availableUnits = $this->getAvailableUnitsForStudent($student, $semester);

        if ($availableUnits->count() < $unitsToTake) {
            $unitsToTake = $availableUnits->count();
        }

        if ($unitsToTake === 0) {
            return;
        }

        $selectedUnits = $availableUnits->random($unitsToTake);

        foreach ($selectedUnits as $unit) {
            $this->createPlannedRegistration($student, $unit, $semester, $progressionType);
        }
    }

    private function getAvailableUnitsForStudent(Student $student, Semester $semester): \Illuminate\Database\Eloquent\Collection
    {
        // Get units from student's curriculum that they haven't completed yet
        $completedUnitIds = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->pluck('unit_id')
            ->toArray();

        // Get curriculum units for next level (simplified logic)
        $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('year_level', '<=', 2) // Limit to first 2 years for this simulation
            ->whereNotIn('unit_id', $completedUnitIds)
            ->with('unit')
            ->get();

        // Extract unit models and return as Unit collection
        $unitIds = $curriculumUnits->pluck('unit_id')->toArray();

        return Unit::whereIn('id', $unitIds)->get();
    }

    private function createPlannedRegistration(Student $student, Unit $unit, Semester $semester, array $progressionType): void
    {
        // This creates a planned registration (not actual registration)
        // In a real system, this might be stored in a separate planning table

        // For demonstration, we'll add this to student notes
        $planNote = "Planned: {$unit->code} in {$semester->code} ({$progressionType['type']})";

        $currentNotes = $student->admission_notes ?? '';
        $student->update([
            'admission_notes' => trim($currentNotes . ' | ' . $planNote),
        ]);
    }

    private function updateStudentProgressionNotes(Student $student, array $progressionType): void
    {
        $progressionNote = "Progression Plan: {$progressionType['description']}";

        $currentNotes = $student->admission_notes ?? '';
        $student->update([
            'admission_notes' => trim($currentNotes . ' | ' . $progressionNote),
        ]);

        $this->command->info("  📋 {$student->student_id}: {$progressionType['description']}");
    }

    private function createSampleFutureSemesters(): \Illuminate\Database\Eloquent\Collection
    {
        // Create sample future semesters for demonstration
        $semesters = collect();

        $semesterData = [
            ['code' => 'SUMMER2025', 'name' => 'Summer 2025', 'start' => '2025-06-01', 'end' => '2025-08-31'],
            ['code' => 'FALL2025', 'name' => 'Fall 2025', 'start' => '2025-09-01', 'end' => '2025-12-15'],
            ['code' => 'SPRING2026', 'name' => 'Spring 2026', 'start' => '2026-02-01', 'end' => '2026-06-30'],
        ];

        foreach ($semesterData as $data) {
            $semester = Semester::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'start_date' => $data['start'],
                    'end_date' => $data['end'],
                    'enrollment_start_date' => date('Y-m-d', strtotime($data['start'] . ' -30 days')),
                    'enrollment_end_date' => date('Y-m-d', strtotime($data['start'] . ' +14 days')),
                    'is_active' => true,
                    'is_current' => false,
                ]
            );

            $semesters->push($semester);
        }

        return $semesters;
    }
}
