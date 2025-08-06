<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Campus;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates campuses, buildings, and rooms for the institution.
     */
    public function run(): void
    {
        $this->command->info('🏛️  Creating institutional infrastructure...');

        // Clean existing data
        $this->cleanExistingData();

        // Create campuses
        $this->createCampuses();

        $this->command->info('✅ Institution setup completed successfully!');
    }

    private function cleanExistingData(): void
    {
        // Delete in order of dependencies (child to parent)
        // Use truncate to avoid foreign key constraint issues during development
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Room::truncate();
        Building::truncate();
        Campus::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createCampuses(): void
    {
        $campuses = [
            [
                'id' => 1,
                'name' => 'Asia Hà Nội',
                'code' => 'HN',
                'address' => 'Số 1 Đường Trần Đăng Ninh, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Thành phố Hà Nội',
            ],
            [
                'id' => 2,
                'name' => 'Asia Hồ Chí Minh',
                'code' => 'HCM',
                'address' => 'Số 123 Đường Nguyễn Văn Cừ, Phường An Hoà, Quận Ninh Kiều, Thành phố Hồ Chí Minh',
            ],
//            [
//                'id' => 3,
//                'name' => 'Swinburne Đà Nẵng',
//                'code' => 'DN',
//                'address' => 'Số 456 Đường Ngô Quyền, Phường An Hải Bắc, Quận Sơn Trà, Thành phố Đà Nẵng',
//            ],
//            [
//                'id' => 4,
//                'name' => 'Swinburne Cần Thơ',
//                'code' => 'CT',
//                'address' => 'Số 789 Đường 3 Tháng 2, Phường Xuân Khánh, Quận Ninh Kiều, Thành phố Cần Thơ',
//            ],
        ];

        foreach ($campuses as $campusData) {
            $campus = Campus::create($campusData);
            $this->createBuildingsForCampus($campus);
            $this->command->info("📍 Created campus: {$campus->name}");
        }
    }

    private function createBuildingsForCampus(Campus $campus): void
    {
        $buildingTypes = [
            'Academic' => [
                'Building A',
            ],
        ];

        $buildingCount = 0;
        foreach ($buildingTypes as $type => $buildings) {
            foreach ($buildings as $buildingName) {
                $buildingCount++;

                $building = Building::create([
                    'campus_id' => $campus->id,
                    'code' => $campus->code . sprintf('%02d', $buildingCount),
                    'name' => $buildingName,
                    'description' => $this->getBuildingDescription($type, $buildingName),
                    'address' => $this->getBuildingAddress($campus, $buildingName),
                ]);

                $this->createRoomsForBuilding($building, $type);
            }
        }
    }

    private function createRoomsForBuilding(Building $building, string $buildingType): void
    {
        $roomConfigs = [
            'Academic' => [
                ['type' => Room::TYPE_CLASSROOM, 'name_prefix' => 'Lecture Hall', 'count' => 5, 'capacity_range' => [80, 150]],
                ['type' => Room::TYPE_CLASSROOM, 'name_prefix' => 'Tutorial Room', 'count' => 8, 'capacity_range' => [25, 40]],
                ['type' => Room::TYPE_LABORATORY, 'name_prefix' => 'Laboratory', 'count' => 6, 'capacity_range' => [30, 45]],
                ['type' => Room::TYPE_MEETING_ROOM, 'name_prefix' => 'Seminar Room', 'count' => 4, 'capacity_range' => [15, 25]],
            ],
            'Administration' => [
                ['type' => Room::TYPE_OFFICE, 'name_prefix' => 'Office', 'count' => 10, 'capacity_range' => [2, 8]],
                ['type' => Room::TYPE_MEETING_ROOM, 'name_prefix' => 'Meeting Room', 'count' => 3, 'capacity_range' => [8, 20]],
            ],
            'Library' => [
                ['type' => Room::TYPE_STUDY_ROOM, 'name_prefix' => 'Study Room', 'count' => 15, 'capacity_range' => [4, 12]],
                ['type' => Room::TYPE_COMPUTER_LAB, 'name_prefix' => 'Computer Lab', 'count' => 3, 'capacity_range' => [20, 40]],
            ],
            'Student' => [
                ['type' => Room::TYPE_OTHER, 'name_prefix' => 'Activity Room', 'count' => 5, 'capacity_range' => [20, 50]],
                ['type' => Room::TYPE_STUDY_ROOM, 'name_prefix' => 'Study Space', 'count' => 8, 'capacity_range' => [4, 15]],
            ],
            'Sports' => [
                ['type' => Room::TYPE_OTHER, 'name_prefix' => 'Gym Hall', 'count' => 2, 'capacity_range' => [50, 100]],
                ['type' => Room::TYPE_OTHER, 'name_prefix' => 'Fitness Room', 'count' => 3, 'capacity_range' => [15, 30]],
            ],
        ];

        $defaultConfig = [['type' => Room::TYPE_OTHER, 'name_prefix' => 'Multi-purpose Room', 'count' => 5, 'capacity_range' => [20, 40]]];
        $configs = $roomConfigs[$buildingType] ?? $defaultConfig;

        $roomNumber = 100;
        foreach ($configs as $config) {
            for ($i = 1; $i <= $config['count']; $i++) {
                $roomNumber++;
                $capacity = rand($config['capacity_range'][0], $config['capacity_range'][1]);
                $floor = (string)floor($roomNumber / 100);

                Room::create([
                    'campus_id' => $building->campus_id,
                    'code' => $building->code . '-' . $roomNumber,
                    'name' => $config['name_prefix'] . ' ' . $roomNumber,
                    'building' => $building->name,
                    'floor' => $floor,
                    'type' => $config['type'],
                    'capacity' => $capacity,
                    'status' => Room::STATUS_AVAILABLE,
                    'is_bookable' => true,
                    'requires_approval' => false,
                    'available_from' => '07:00:00',
                    'available_until' => '18:00:00',
                    'description' => "A {$config['name_prefix']} with capacity for {$capacity} people",
                ]);
            }
        }
    }

    private function getBuildingDescription(string $type, string $name): string
    {
        $descriptions = [
            'Academic' => 'Modern academic facility equipped with state-of-the-art classrooms, laboratories, and research facilities.',
            'Administration' => 'Administrative building housing various university departments and services.',
            'Library' => 'Comprehensive library facility providing extensive collection of books, digital resources, and study spaces.',
            'Student' => 'Student-centered facility designed to support campus life and student activities.',
            'Sports' => 'Athletic facility promoting health, wellness, and sports activities for the university community.',
        ];

        return $descriptions[$type] ?? 'University building serving various academic and administrative purposes.';
    }

    private function getBuildingAddress(Campus $campus, string $buildingName): string
    {
        return $buildingName . ', ' . $campus->address;
    }
}
