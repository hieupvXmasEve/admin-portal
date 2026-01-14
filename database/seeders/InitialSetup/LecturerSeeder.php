<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Lecture;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LecturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 20 lecturers with user accounts and profiles
     */
    public function run(): void
    {
        $this->command->info('👨‍🏫 Creating lecturers...');

        // Clean existing data
        $this->cleanExistingData();

        // Create lecturers
        $this->createLecturers();

        $this->command->info('✅ Lecturers created successfully!');
    }

    private function cleanExistingData(): void
    {
        // Clean lecturer-related data
        // Use truncate to avoid foreign key constraint issues during development
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clean lecturer-related data
        $lecturerRole = Role::where('code', 'giang_vien')->first();

        if ($lecturerRole) {
            // Get all users with lecturer role
            $lecturerUsers = User::whereHas('campusRoles', function ($query) use ($lecturerRole) {
                $query->where('role_id', $lecturerRole->id);
            })->pluck('id');

            // Delete lecturer profiles
            Lecture::truncate();

            // Delete campus user role assignments for lecturers
            CampusUserRole::where('role_id', $lecturerRole->id)->delete();

            // Delete lecturer user accounts
            if ($lecturerUsers->isNotEmpty()) {
                User::whereIn('id', $lecturerUsers)->delete();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createLecturers(): void
    {
        $campuses = Campus::all();
        $lecturerRole = Role::where('code', 'giang_vien')->first();

        $lecturerData = [
            ['name' => 'Mr. Phạm Văn Hiếu', 'email' => 'hieunucek6@gmail.com', 'department' => 'Computer Science', 'specialization' => 'Software Engineering'],
            ['name' => 'Prof. Trần Thị Bình', 'email' => 'tran.thi.binh@swinburne.edu.vn', 'department' => 'Computer Science', 'specialization' => 'Artificial Intelligence'],
            ['name' => 'Dr. Lê Minh Cường', 'email' => 'le.minh.cuong@swinburne.edu.vn', 'department' => 'Computer Science', 'specialization' => 'Cybersecurity'],
            ['name' => 'Ms. Phạm Thị Dung', 'email' => 'pham.thi.dung@swinburne.edu.vn', 'department' => 'Computer Science', 'specialization' => 'Database Systems'],
            ['name' => 'Dr. Hoàng Văn Em', 'email' => 'hoang.van.em@swinburne.edu.vn', 'department' => 'Computer Science', 'specialization' => 'Web Development'],
            ['name' => 'Prof. Vũ Thị Phương', 'email' => 'vu.thi.phuong@swinburne.edu.vn', 'department' => 'Business', 'specialization' => 'Marketing'],
            ['name' => 'Dr. Đặng Minh Giang', 'email' => 'dang.minh.giang@swinburne.edu.vn', 'department' => 'Business', 'specialization' => 'Finance'],
            ['name' => 'Ms. Bùi Thị Hoa', 'email' => 'bui.thi.hoa@swinburne.edu.vn', 'department' => 'Business', 'specialization' => 'Human Resources'],
            ['name' => 'Dr. Ngô Văn Inh', 'email' => 'ngo.van.inh@swinburne.edu.vn', 'department' => 'Business', 'specialization' => 'Operations Management'],
            ['name' => 'Prof. Lý Thị Kim', 'email' => 'ly.thi.kim@swinburne.edu.vn', 'department' => 'Business', 'specialization' => 'Strategic Management'],
            ['name' => 'Dr. Trịnh Văn Long', 'email' => 'trinh.van.long@swinburne.edu.vn', 'department' => 'Engineering', 'specialization' => 'Civil Engineering'],
            ['name' => 'Ms. Đinh Thị Mai', 'email' => 'dinh.thi.mai@swinburne.edu.vn', 'department' => 'Engineering', 'specialization' => 'Mechanical Engineering'],
            ['name' => 'Dr. Võ Minh Nam', 'email' => 'vo.minh.nam@swinburne.edu.vn', 'department' => 'Engineering', 'specialization' => 'Electrical Engineering'],
            ['name' => 'Prof. Đỗ Thị Oanh', 'email' => 'do.thi.oanh@swinburne.edu.vn', 'department' => 'Mathematics', 'specialization' => 'Applied Mathematics'],
            ['name' => 'Dr. Lương Văn Phúc', 'email' => 'luong.van.phuc@swinburne.edu.vn', 'department' => 'Mathematics', 'specialization' => 'Statistics'],
            ['name' => 'Ms. Chu Thị Quỳnh', 'email' => 'chu.thi.quynh@swinburne.edu.vn', 'department' => 'English', 'specialization' => 'Academic English'],
            ['name' => 'Dr. Phan Văn Rồng', 'email' => 'phan.van.rong@swinburne.edu.vn', 'department' => 'English', 'specialization' => 'Business English'],
            ['name' => 'Prof. Mai Thị Sương', 'email' => 'mai.thi.suong@swinburne.edu.vn', 'department' => 'Physics', 'specialization' => 'Applied Physics'],
            ['name' => 'Dr. Hồ Văn Tùng', 'email' => 'ho.van.tung@swinburne.edu.vn', 'department' => 'Chemistry', 'specialization' => 'Organic Chemistry'],
            ['name' => 'Ms. Dương Thị Uyên', 'email' => 'duong.thi.uyen@swinburne.edu.vn', 'department' => 'Psychology', 'specialization' => 'Educational Psychology'],
        ];

        foreach ($lecturerData as $index => $lecturer) {
            // Create user account for lecturer
            $user = User::create([
                'name' => $lecturer['name'],
                'email' => $lecturer['email'],
                'password' => Hash::make('123456'),
                'type' => \App\Shared\Support\Enums\UserType::LECTURER,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            // Assign lecturer role to a random campus
            $campus = $campuses->random();
            CampusUserRole::create([
                'user_id' => $user->id,
                'campus_id' => $campus->id,
                'role_id' => $lecturerRole->id,
            ]);

            // Create lecturer profile
            $this->createLecturerProfile($user, $lecturer, $campus, $index + 1);

            $this->command->info("👨‍🏫 Created lecturer: {$lecturer['name']} at {$campus->name}");
        }
    }

    private function createLecturerProfile(User $user, array $lecturerData, Campus $campus, int $index): void
    {
        $nameParts = explode(' ', str_replace(['Dr. ', 'Prof. ', 'Ms. ', 'Mr. '], '', $lecturerData['name']));
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);

        $academicRanks = ['lecturer', 'senior_lecturer', 'associate_professor', 'professor'];
        $employmentTypes = ['full_time', 'part_time', 'contract'];
        $degrees = ['PhD', 'Master', 'Bachelor'];

        Lecture::create([
            'user_id' => $user->id,
            'employee_id' => 'LEC'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'title' => $this->extractTitle($lecturerData['name']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $lecturerData['email'],
            'phone' => '+84 '.rand(100000000, 999999999),
            'mobile_phone' => '+84 '.rand(900000000, 999999999),
            'campus_id' => $campus->id,
            'department' => $lecturerData['department'],
            'faculty' => $this->getFacultyFromDepartment($lecturerData['department']),
            'specialization' => $lecturerData['specialization'],
            'expertise_areas' => [$lecturerData['specialization'], 'Research', 'Teaching'],
            'academic_rank' => $academicRanks[array_rand($academicRanks)],
            'highest_degree' => $degrees[array_rand($degrees)],
            'degree_field' => $lecturerData['specialization'],
            'alma_mater' => $this->getRandomUniversity(),
            'graduation_year' => rand(1995, 2015),
            'hire_date' => now()->subYears(rand(1, 10)),
            'contract_start_date' => now()->subYears(rand(1, 5)),
            'contract_end_date' => now()->addYears(rand(1, 5)),
            'employment_type' => $employmentTypes[array_rand($employmentTypes)],
            'employment_status' => 'active',
            'preferred_teaching_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'preferred_start_time' => now()->setTime(8, 0),
            'preferred_end_time' => now()->setTime(17, 0),
            'max_teaching_hours_per_week' => rand(15, 25),
            'teaching_modalities' => ['in_person', 'online', 'hybrid'],
            'office_address' => 'Room '.rand(100, 999).', '.$campus->name,
            'office_phone' => '+84 '.rand(100000000, 999999999),
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '+84 '.rand(900000000, 999999999),
            'emergency_contact_relationship' => 'Spouse',
            'biography' => "Experienced educator in {$lecturerData['specialization']} with extensive research background.",
            'certifications' => ['Teaching Certificate', 'Professional Development'],
            'languages' => ['Vietnamese', 'English'],
            'hourly_rate' => rand(500000, 1000000),
            'salary' => rand(20000000, 50000000),
            'is_active' => true,
            'can_teach_online' => true,
            'is_available_for_assignment' => true,
            'notes' => 'Created by seeder',
        ]);
    }

    private function extractTitle(string $name): ?string
    {
        if (str_contains($name, 'Dr.')) {
            return 'Dr.';
        }
        if (str_contains($name, 'Prof.')) {
            return 'Prof.';
        }
        if (str_contains($name, 'Ms.')) {
            return 'Ms.';
        }
        if (str_contains($name, 'Mr.')) {
            return 'Mr.';
        }

        return null;
    }

    private function getFacultyFromDepartment(string $department): string
    {
        $facultyMap = [
            'Computer Science' => 'Faculty of Science and Technology',
            'Business' => 'Faculty of Business',
            'Engineering' => 'Faculty of Engineering',
            'Mathematics' => 'Faculty of Science and Technology',
            'English' => 'Faculty of Arts and Humanities',
            'Physics' => 'Faculty of Science and Technology',
            'Chemistry' => 'Faculty of Science and Technology',
            'Psychology' => 'Faculty of Arts and Humanities',
        ];

        return $facultyMap[$department] ?? 'Faculty of General Studies';
    }

    private function getRandomUniversity(): string
    {
        $universities = [
            'Swinburne University of Technology',
            'University of Melbourne',
            'Monash University',
            'RMIT University',
            'Deakin University',
            'La Trobe University',
            'Griffith University',
            'Queensland University of Technology',
        ];

        return $universities[array_rand($universities)];
    }
}
