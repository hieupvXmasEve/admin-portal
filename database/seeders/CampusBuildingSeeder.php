<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Building;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CampusBuildingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create campuses if they don't exist
        $campuses = [
            [
                'name' => 'Swinburne Hà Nội',
                'code' => 'HN',
                'address' => 'Số 1 Đường Trần Đăng Ninh, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Thành phố Hà Nội',
            ],
            [
                'name' => 'Swinburne Hồ Chí Minh',
                'code' => 'HCM',
                'address' => 'Số 123 Đường Nguyễn Văn Cừ, Phường An Hoà, Quận Ninh Kiều, Thành phố Hồ Chí Minh',
            ],
            [
                'name' => 'Swinburne Đà Nẵng',
                'code' => 'DN',
                'address' => 'Số 456 Đường Ngô Quyền, Phường An Hải Bắc, Quận Sơn Trà, Thành phố Đà Nẵng',
            ],
            [
                'name' => 'Swinburne Cần Thơ',
                'code' => 'CT',
                'address' => 'Số 789 Đường 3 Tháng 2, Phường Xuân Khánh, Quận Ninh Kiều, Thành phố Cần Thơ',
            ],
        ];

        foreach ($campuses as $campusData) {
            $campus = Campus::firstOrCreate(
                ['code' => $campusData['code']],
                $campusData
            );

            // Create buildings for each campus
            $this->createBuildingsForCampus($campus);
        }
    }

    private function createBuildingsForCampus(Campus $campus): void
    {
        $buildingTypes = [
            'Academic' => [
                'Science Building A',
                'Science Building B',
                'Engineering Block',
                'Business Faculty',
                'Computer Science Center',
            ],
            'Administration' => [
                'Main Administration',
                'Student Services',
                'Finance Office',
            ],
            'Library' => [
                'Central Library',
                'Digital Learning Center',
            ],
            'Student' => [
                'Student Center',
                'Recreation Center',
                'Food Court',
            ],
            'Sports' => [
                'Gymnasium',
                'Sports Complex',
            ],
        ];

        $buildingCount = 0;
        foreach ($buildingTypes as $type => $buildings) {
            foreach ($buildings as $buildingName) {
                $buildingCount++;

                Building::firstOrCreate([
                    'campus_id' => $campus->id,
                    'code' => $campus->code . sprintf('%02d', $buildingCount),
                ], [
                    'name' => $buildingName,
                    'description' => $this->getBuildingDescription($type, $buildingName),
                    'address' => $this->getBuildingAddress($campus, $buildingName),
                ]);
            }
        }
    }

    private function getBuildingDescription(string $type, string $name): string
    {
        $descriptions = [
            'Academic' => 'Modern academic facility equipped with state-of-the-art classrooms, laboratories, and research facilities for ' . strtolower($name) . '.',
            'Administration' => 'Administrative building housing various university departments and services including ' . strtolower($name) . '.',
            'Library' => 'Comprehensive library facility providing extensive collection of books, digital resources, and study spaces.',
            'Student' => 'Student-centered facility designed to support campus life and student activities.',
            'Sports' => 'Athletic facility promoting health, wellness, and sports activities for the university community.',
        ];

        return $descriptions[$type] ?? 'University building serving various academic and administrative purposes.';
    }

    private function getBuildingAddress(Campus $campus, string $buildingName): string
    {
        // Generate specific building addresses based on campus location
        $buildingAddresses = [
            'HN' => [
                'Science Building A' => 'Block A, ' . $campus->address,
                'Science Building B' => 'Block B, ' . $campus->address,
                'Engineering Block' => 'Block C, ' . $campus->address,
                'Business Faculty' => 'Block D, ' . $campus->address,
                'Computer Science Center' => 'Block E, ' . $campus->address,
                'Main Administration' => 'Administrative Building, ' . $campus->address,
                'Student Services' => 'Student Services Building, ' . $campus->address,
                'Central Library' => 'Library Building, ' . $campus->address,
                'Student Center' => 'Student Activities Center, ' . $campus->address,
                'Gymnasium' => 'Sports Complex, ' . $campus->address,
            ],
        ];

        // Use specific address if available, otherwise generate generic one
        if (isset($buildingAddresses[$campus->code][$buildingName])) {
            return $buildingAddresses[$campus->code][$buildingName];
        }

        return $buildingName . ', ' . $campus->address;
    }
}
