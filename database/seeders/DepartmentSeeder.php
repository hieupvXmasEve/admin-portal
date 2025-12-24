<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'HQ',
                'name' => 'Office of the Registrar / Headquarters',
                'description' => 'Handles student records, admissions, and academic administration.',
            ],
            [
                'code' => 'IT',
                'name' => 'Information Technology',
                'description' => 'Technical support, system administration, and infrastructure.',
            ],
            [
                'code' => 'FIN',
                'name' => 'Finance & Accounting',
                'description' => 'Tuition fees, payroll, and financial management.',
            ],
            [
                'code' => 'SA',
                'name' => 'Student Affairs',
                'description' => 'Student life, clubs, events, and support services.',
            ],
            [
                'code' => 'ACAD',
                'name' => 'Academic Affairs',
                'description' => 'Curriculum development, teaching quality, and faculty management.',
            ],
            [
                'code' => 'LIB',
                'name' => 'Library',
                'description' => 'Digital and physical learning resources management.',
            ],
            [
                'code' => 'FAC',
                'name' => 'Facilities Management',
                'description' => 'Campus maintenance, security, and cleaning services.',
            ],
            [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'Staff recruitment, benefits, and administrative support.',
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                [
                    'name' => $dept['name'],
                    'description' => $dept['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
