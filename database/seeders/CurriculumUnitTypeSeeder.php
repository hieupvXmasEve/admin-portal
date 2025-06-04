<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CurriculumUnitType;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CurriculumUnitTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'core',
            ],
            [
                'name' => 'elective',
            ],
            [
                'name' => 'major',
            ],
        ];

        foreach ($types as $type) {
            CurriculumUnitType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        $this->command->info('CurriculumUnitType seeding completed!');
    }
}
