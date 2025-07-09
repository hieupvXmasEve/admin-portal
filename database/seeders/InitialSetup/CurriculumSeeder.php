<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Program;
use App\Models\Specialization;
use App\Models\Unit;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates curriculum versions and assigns units to programs and specializations
     */
    public function run(): void
    {
        $this->command->info('📚 Creating curriculum versions...');

        // Clean existing data
        $this->cleanExistingData();

        // Create curriculum versions for each specialization
        $this->createCurriculumVersions();

        $this->command->info('✅ Curriculum versions created successfully!');
    }

    private function cleanExistingData(): void
    {
        CurriculumUnit::query()->delete();
        CurriculumVersion::query()->delete();
    }

    private function createCurriculumVersions(): void
    {
        $specializations = Specialization::with('program')->get();

        foreach ($specializations as $specialization) {
            $this->createCurriculumForSpecialization($specialization);
        }
    }

    private function createCurriculumForSpecialization(Specialization $specialization): void
    {
        // Create a temporary/default semester if none exists
        $defaultSemester = \App\Models\Semester::firstOrCreate(
            ['code' => 'DEFAULT2025'],
            [
                'name' => 'Default Academic Year 2025',
                'start_date' => now()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'enrollment_start_date' => now()->startOfYear(),
                'enrollment_end_date' => now()->addMonths(2),
                'is_active' => false,
                'is_archived' => false,
            ]
        );

        // Create curriculum version
        $curriculumVersion = CurriculumVersion::create([
            'program_id' => $specialization->program_id,
            'specialization_id' => $specialization->id,
            'version_code' => '2025.1',
            'semester_id' => $defaultSemester->id,
            'notes' => "Initial curriculum version for {$specialization->name}",
        ]);

        // Assign units based on specialization
        $this->assignUnitsToSpecialization($curriculumVersion, $specialization);

        $this->command->info("📖 Created curriculum for {$specialization->name}");
    }

    private function assignUnitsToSpecialization(CurriculumVersion $curriculumVersion, Specialization $specialization): void
    {
        $programCode = $specialization->program->code;
        $specializationCode = $specialization->code;

        // Define unit assignments based on program and specialization
        switch ($programCode) {
            case 'BIT':
                $this->assignITUnits($curriculumVersion, $specializationCode);
                break;
            case 'BBus':
                $this->assignBusinessUnits($curriculumVersion, $specializationCode);
                break;
            case 'BEng':
                $this->assignEngineeringUnits($curriculumVersion, $specializationCode);
                break;
        }
    }

    private function assignITUnits(CurriculumVersion $curriculumVersion, string $specializationCode): void
    {
        // Common IT foundation units (Year 1)
        $foundationUnits = [
            ['code' => 'COS10009', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'COS10011', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'MAT10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ENG10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'COS10026', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS20007', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'COS20024', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'MAT20001', 'year' => 1, 'semester' => 2, 'type' => 'core'],
        ];

        // Specialization-specific units (Years 2-3)
        $specializationUnits = [];
        switch ($specializationCode) {
            case 'SD': // Software Development
                $specializationUnits = [
                    ['code' => 'COS30043', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS20019', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS30020', 'year' => 2, 'semester' => 2, 'type' => 'major'],
                    ['code' => 'COS30019', 'year' => 2, 'semester' => 2, 'type' => 'major'],
                    ['code' => 'COS40002', 'year' => 3, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS40003', 'year' => 3, 'semester' => 2, 'type' => 'major'],
                ];
                break;
            case 'CS': // Cybersecurity
                $specializationUnits = [
                    ['code' => 'COS20014', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS30027', 'year' => 2, 'semester' => 2, 'type' => 'major'],
                    ['code' => 'COS40005', 'year' => 3, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'TNE10005', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'DS': // Data Science
                $specializationUnits = [
                    ['code' => 'COS30019', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS30018', 'year' => 2, 'semester' => 2, 'type' => 'major'],
                    ['code' => 'COS40007', 'year' => 3, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'MAT30001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'NE': // Network Engineering
                $specializationUnits = [
                    ['code' => 'TNE10005', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                    ['code' => 'COS20019', 'year' => 2, 'semester' => 2, 'type' => 'major'],
                    ['code' => 'COS20014', 'year' => 3, 'semester' => 1, 'type' => 'major'],
                ];
                break;
        }

        // Capstone units (Year 4)
        $capstoneUnits = [
            ['code' => 'COS40001', 'year' => 4, 'semester' => 1, 'type' => 'major'], // Capstone project
            ['code' => 'ENG30001', 'year' => 4, 'semester' => 1, 'type' => 'core'],
        ];

        // Combine all units
        $allUnits = array_merge($foundationUnits, $specializationUnits, $capstoneUnits);
        $this->createCurriculumUnits($curriculumVersion, $allUnits);
    }

    private function assignBusinessUnits(CurriculumVersion $curriculumVersion, string $specializationCode): void
    {
        // Common Business foundation units (Year 1)
        $foundationUnits = [
            ['code' => 'HRM10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'MKT10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ACC10007', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ENG10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ECO10004', 'year' => 1, 'semester' => 2, 'type' => 'core'],
            ['code' => 'ENG20001', 'year' => 1, 'semester' => 2, 'type' => 'core'],
        ];

        // Specialization-specific units
        $specializationUnits = [];
        switch ($specializationCode) {
            case 'MKT': // Marketing
                $specializationUnits = [
                    ['code' => 'MKT20001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'FIN': // Finance
                $specializationUnits = [
                    ['code' => 'FIN20001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'HR': // Human Resources
                $specializationUnits = [
                    ['code' => 'HRM10001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'IB': // International Business
                $specializationUnits = [
                    ['code' => 'ECO10004', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
        }

        // Capstone units
        $capstoneUnits = [
            ['code' => 'BUS40001', 'year' => 4, 'semester' => 1, 'type' => 'major'], // Capstone project
        ];

        $allUnits = array_merge($foundationUnits, $specializationUnits, $capstoneUnits);
        $this->createCurriculumUnits($curriculumVersion, $allUnits);
    }

    private function assignEngineeringUnits(CurriculumVersion $curriculumVersion, string $specializationCode): void
    {
        // Common Engineering foundation units
        $foundationUnits = [
            ['code' => 'ENG10002', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ENG10003', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'MAT10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
            ['code' => 'ENG10001', 'year' => 1, 'semester' => 1, 'type' => 'core'],
        ];

        // Specialization-specific units
        $specializationUnits = [];
        switch ($specializationCode) {
            case 'CE': // Civil Engineering
                $specializationUnits = [
                    ['code' => 'CIV20001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'ME': // Mechanical Engineering
                $specializationUnits = [
                    ['code' => 'MEC20001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
            case 'EE': // Electrical Engineering
                $specializationUnits = [
                    ['code' => 'ELE20001', 'year' => 2, 'semester' => 1, 'type' => 'major'],
                ];
                break;
        }

        // Capstone units
        $capstoneUnits = [
            ['code' => 'ENG40001', 'year' => 4, 'semester' => 1, 'type' => 'major'], // Capstone project
        ];

        $allUnits = array_merge($foundationUnits, $specializationUnits, $capstoneUnits);
        $this->createCurriculumUnits($curriculumVersion, $allUnits);
    }

    private function createCurriculumUnits(CurriculumVersion $curriculumVersion, array $unitData): void
    {
        foreach ($unitData as $unitInfo) {
            $unit = Unit::where('code', $unitInfo['code'])->first();
            if (!$unit) {
                $this->command->warn("Unit {$unitInfo['code']} not found, skipping...");
                continue;
            }

            // Check if this unit is already assigned to this curriculum version
            $existingUnit = CurriculumUnit::where('curriculum_version_id', $curriculumVersion->id)
                ->where('unit_id', $unit->id)
                ->first();

            if ($existingUnit) {
                $this->command->warn("Unit {$unitInfo['code']} already assigned to {$curriculumVersion->specialization->name}, skipping...");
                continue;
            }

            CurriculumUnit::create([
                'curriculum_version_id' => $curriculumVersion->id,
                'unit_id' => $unit->id,
                'semester_id' => $curriculumVersion->semester_id,
                'type' => $unitInfo['type'],
                'year_level' => $unitInfo['year'],
                'semester_number' => $unitInfo['semester'],
                'note' => "Assigned to {$curriculumVersion->specialization->name}",
            ]);
        }
    }
}
