<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates special user accounts: Super Admin, Campus Admins, and Staff
     */
    public function run(): void
    {
        $this->command->info('👤 Creating user accounts...');

        // Clean existing data
        $this->cleanExistingData();

        // Create super admin user
        $this->createSuperAdminUser();

        // Create campus admin users
        $this->createCampusAdminUsers();

        // Create staff users
        $this->createStaffUsers();

        // Create additional test users in local environment
        $this->createTestUsers();

        $this->command->info('✅ User accounts created successfully!');
    }

    private function cleanExistingData(): void
    {
        CampusUserRole::query()->delete();
        User::query()->delete();
    }

    private function createSuperAdminUser(): void
    {
        $superAdmin = User::create([
            'id' => 2,
            'name' => 'Super Admin 2',
            'email' => 'hieupv2412@gmail.com',
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        // Assign super admin to all campuses
        $this->assignUserToAllCampuses($superAdmin, 'super_admin');

        $this->command->info('🔑 Created Super Admin user');
    }

    private function createCampusAdminUsers(): void
    {
        $campuses = Campus::all();
        $adminRole = Role::where('code', 'giam_doc_dao_tao')->first();

        $adminUsers = [
            ['name' => 'Admin Hà Nội', 'email' => 'admin.hanoi@swinburne.edu.vn'],
            ['name' => 'Admin Hồ Chí Minh', 'email' => 'admin.hcm@swinburne.edu.vn'],
            ['name' => 'Admin Đà Nẵng', 'email' => 'admin.danang@swinburne.edu.vn'],
            ['name' => 'Admin Cần Thơ', 'email' => 'admin.cantho@swinburne.edu.vn'],
        ];

        foreach ($campuses as $index => $campus) {
            if (isset($adminUsers[$index])) {
                $adminData = $adminUsers[$index];

                $admin = User::create([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ]);

                // Assign admin to their specific campus
                CampusUserRole::create([
                    'user_id' => $admin->id,
                    'campus_id' => $campus->id,
                    'role_id' => $adminRole->id,
                ]);

                $this->command->info("👨‍💼 Created admin for {$campus->name}");
            }
        }
    }

    private function createStaffUsers(): void
    {
        $campuses = Campus::all();
        $staffRoles = [
            Role::where('code', 'truong_phong')->first(),
            Role::where('code', 'can_bo')->first(),
        ];

        $staffUsers = [
            ['name' => 'Trưởng Phòng Đào Tạo', 'email' => 'truongphong.daotao@swinburne.edu.vn', 'role' => 'truong_phong'],
            ['name' => 'Cán Bộ Sinh Viên', 'email' => 'canbo.sinhvien@swinburne.edu.vn', 'role' => 'can_bo'],
            ['name' => 'Cán Bộ Học Vụ', 'email' => 'canbo.hocvu@swinburne.edu.vn', 'role' => 'can_bo'],
            ['name' => 'Cán Bộ Tài Chính', 'email' => 'canbo.taichinh@swinburne.edu.vn', 'role' => 'can_bo'],
            ['name' => 'Cán Bộ Thư Viện', 'email' => 'canbo.thuvien@swinburne.edu.vn', 'role' => 'can_bo'],
        ];

        foreach ($staffUsers as $staffData) {
            $staff = User::create([
                'name' => $staffData['name'],
                'email' => $staffData['email'],
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
            ]);

            $role = Role::where('code', $staffData['role'])->first();

            // Assign staff to all campuses (they can work across campuses)
            foreach ($campuses as $campus) {
                CampusUserRole::create([
                    'user_id' => $staff->id,
                    'campus_id' => $campus->id,
                    'role_id' => $role->id,
                ]);
            }

            $this->command->info("👨‍💻 Created staff: {$staff->name}");
        }
    }

    private function createTestUsers(): void
    {
        if (app()->environment('local')) {
            User::factory(50)->create();
            $this->command->info('🧪 Created 50 test users for local environment');
        }
    }

    private function assignUserToAllCampuses(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->first();
        $campuses = Campus::all();

        foreach ($campuses as $campus) {
            CampusUserRole::create([
                'user_id' => $user->id,
                'campus_id' => $campus->id,
                'role_id' => $role->id,
            ]);
        }
    }
}
