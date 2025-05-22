<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::query()->delete();
        Permission::query()->delete();
        RolePermission::query()->delete();
        Campus::query()->delete();
        User::query()->delete();
        $now = now();

        $campus = [
            ['id' => 1, 'name' => 'Swinburne Hà Nội', 'code' => 'HN', 'address' => ''],
            ['id' => 2, 'name' => 'Swinburne Hồ Chí Minh', 'code' => 'HCM', 'address' => ''],
            ['id' => 3, 'name' => 'Swinburne Đà Năng', 'code' => 'DN', 'address' => ''],
            ['id' => 4, 'name' => 'Swinburne Cần Thơ', 'code' => 'CT', 'address' => ''],
        ];
        foreach ($campus as &$c) {
            $c['created_at'] = $now;
            $c['updated_at'] = $now;
        }
        unset($c);
        Campus::insert($campus);

        $roles = [
            ['id' => 1, 'name' => 'Super Admin'],
            ['id' => 2, 'name' => 'Giám Đốc Đào Tạo'],
            ['id' => 3, 'name' => 'Trường Phòng Student HQ'],
            ['id' => 4, 'name' => 'Trường Phòng Hành Chính'],
            ['id' => 5, 'name' => 'Trường Ban Tuyển Sinh'],
            ['id' => 6, 'name' => 'Trường Phòng Tuyển Sinh'],
            ['id' => 7, 'name' => 'Cán Bộ Đào Tạo'],
            ['id' => 8, 'name' => 'Cán Bộ Student HQ'],
            ['id' => 9, 'name' => 'Cán Bộ Hành Chính'],
            ['id' => 10, 'name' => 'Cán Bộ Tuyển Sinh'],
            ['id' => 11, 'name' => 'Giảng Viên'],
        ];
        foreach ($roles as &$role) {
            $role['created_at'] = $now;
            $role['updated_at'] = $now;
        }
        unset($role);
        Role::insert($roles);
        $permissions = [
            ['id' => 1, 'name' => 'User Management', 'parent_id' => null, 'code' => 'user_management'],
            ['id' => 2, 'name' => 'View User', 'parent_id' => 1, 'code' => 'view_user'],
            ['id' => 3, 'name' => 'Add User', 'parent_id' => 1, 'code' => 'add_user'],
            ['id' => 4, 'name' => 'Edit User', 'parent_id' => 1, 'code' => 'edit_user'],
            ['id' => 5, 'name' => 'Delete User', 'parent_id' => 1, 'code' => 'delete_user'],

            ['id' => 6, 'name' => 'Room', 'parent_id' => null, 'code' => 'room'],
            ['id' => 7, 'name' => 'View Room', 'parent_id' => 6, 'code' => 'view_room'],
            ['id' => 8, 'name' => 'Add Room', 'parent_id' => 6, 'code' => 'add_room'],
            ['id' => 9, 'name' => 'Edit Room', 'parent_id' => 6, 'code' => 'edit_room'],
            ['id' => 10, 'name' => 'Delete Room', 'parent_id' => 6, 'code' => 'delete_room'],

            ['id' => 11, 'name' => 'Course', 'parent_id' => null, 'code' => 'course'],
            ['id' => 12, 'name' => 'View Course', 'parent_id' => 11, 'code' => 'view_course'],
            ['id' => 13, 'name' => 'Add Course', 'parent_id' => 11, 'code' => 'add_course'],
            ['id' => 14, 'name' => 'Edit Course', 'parent_id' => 11, 'code' => 'edit_course'],
            ['id' => 15, 'name' => 'Delete Course', 'parent_id' => 11, 'code' => 'delete_course'],

            ['id' => 16, 'name' => 'Terms', 'parent_id' => null, 'code' => 'terms'],
            ['id' => 17, 'name' => 'View Terms', 'parent_id' => 16, 'code' => 'view_terms'],
            ['id' => 18, 'name' => 'Add Terms', 'parent_id' => 16, 'code' => 'add_terms'],
            ['id' => 19, 'name' => 'Edit Terms', 'parent_id' => 16, 'code' => 'edit_terms'],
            ['id' => 20, 'name' => 'Delete Terms', 'parent_id' => 16, 'code' => 'delete_terms'],

            ['id' => 26, 'name' => 'Groups', 'parent_id' => null, 'code' => 'groups'],
            ['id' => 27, 'name' => 'View Groups', 'parent_id' => 26, 'code' => 'view_groups'],
            ['id' => 28, 'name' => 'Add Groups', 'parent_id' => 26, 'code' => 'add_groups'],
            ['id' => 29, 'name' => 'Add Student Group', 'parent_id' => 26, 'code' => 'add_student_group'],
            ['id' => 30, 'name' => 'Edit Groups', 'parent_id' => 26, 'code' => 'edit_groups'],
            ['id' => 31, 'name' => 'Delete Groups', 'parent_id' => 26, 'code' => 'delete_groups'],

            ['id' => 32, 'name' => 'Events', 'parent_id' => null, 'code' => 'events'],
            ['id' => 33, 'name' => 'View Events', 'parent_id' => 32, 'code' => 'view_events'],
            ['id' => 34, 'name' => 'Add Events', 'parent_id' => 32, 'code' => 'add_events'],
            ['id' => 35, 'name' => 'Edit Events', 'parent_id' => 32, 'code' => 'edit_events'],
            ['id' => 36, 'name' => 'Delete Events', 'parent_id' => 32, 'code' => 'delete_events'],

            ['id' => 37, 'name' => 'Clubs', 'parent_id' => null, 'code' => 'clubs'],
            ['id' => 38, 'name' => 'View Clubs', 'parent_id' => 37, 'code' => 'view_clubs'],
            ['id' => 39, 'name' => 'Add Clubs', 'parent_id' => 37, 'code' => 'add_clubs'],
            ['id' => 40, 'name' => 'Edit Clubs', 'parent_id' => 37, 'code' => 'edit_clubs'],
            ['id' => 41, 'name' => 'Delete Clubs', 'parent_id' => 37, 'code' => 'delete_clubs'],

            ['id' => 42, 'name' => 'Items', 'parent_id' => null, 'code' => 'items'],
            ['id' => 43, 'name' => 'View Items', 'parent_id' => 42, 'code' => 'view_items'],
            ['id' => 44, 'name' => 'Add Items', 'parent_id' => 42, 'code' => 'add_items'],
            ['id' => 45, 'name' => 'Edit Items', 'parent_id' => 42, 'code' => 'edit_items'],
            ['id' => 46, 'name' => 'Delete Items', 'parent_id' => 42, 'code' => 'delete_items'],

            ['id' => 47, 'name' => 'Fees', 'parent_id' => null, 'code' => 'fees'],
            ['id' => 48, 'name' => 'View Fees', 'parent_id' => 47, 'code' => 'view_fees'],
            ['id' => 49, 'name' => 'Add Fees', 'parent_id' => 47, 'code' => 'add_fees'],
            ['id' => 50, 'name' => 'Import Fees', 'parent_id' => 47, 'code' => 'import_fees'],

            ['id' => 51, 'name' => 'Queries', 'parent_id' => null, 'code' => 'queries'],
            ['id' => 52, 'name' => 'View Queries', 'parent_id' => 51, 'code' => 'view_queries'],
            ['id' => 53, 'name' => 'Detail Queries', 'parent_id' => 51, 'code' => 'detail_queries'],

            ['id' => 57, 'name' => 'Golds', 'parent_id' => null, 'code' => 'golds'],
            ['id' => 58, 'name' => 'View Golds', 'parent_id' => 57, 'code' => 'view_golds'],
            ['id' => 59, 'name' => 'Add Golds', 'parent_id' => 57, 'code' => 'add_golds'],
            ['id' => 60, 'name' => 'Edit Golds', 'parent_id' => 57, 'code' => 'edit_golds'],
            ['id' => 61, 'name' => 'Detail Golds', 'parent_id' => 57, 'code' => 'detail_golds'],
        ];
        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }
        unset($permission);

        Permission::insert($permissions);
        $rolePermissions = [
            ['id' => 2, 'role_id' => 1, 'permission_id' => 3],
            ['id' => 3, 'role_id' => 1, 'permission_id' => 4],
            ['id' => 4, 'role_id' => 1, 'permission_id' => 5],
            ['id' => 6, 'role_id' => 1, 'permission_id' => 8],
            ['id' => 7, 'role_id' => 1, 'permission_id' => 9],
            ['id' => 8, 'role_id' => 1, 'permission_id' => 10],
            ['id' => 9, 'role_id' => 1, 'permission_id' => 12],
            ['id' => 10, 'role_id' => 1, 'permission_id' => 13],
            ['id' => 11, 'role_id' => 1, 'permission_id' => 14],
            ['id' => 12, 'role_id' => 1, 'permission_id' => 15],
            ['id' => 14, 'role_id' => 1, 'permission_id' => 18],
            ['id' => 15, 'role_id' => 1, 'permission_id' => 19],
            ['id' => 16, 'role_id' => 1, 'permission_id' => 20],
            ['id' => 21, 'role_id' => 1, 'permission_id' => 27],
            ['id' => 22, 'role_id' => 1, 'permission_id' => 28],
            ['id' => 23, 'role_id' => 1, 'permission_id' => 29],
            ['id' => 24, 'role_id' => 1, 'permission_id' => 30],
            ['id' => 25, 'role_id' => 1, 'permission_id' => 31],
            ['id' => 27, 'role_id' => 1, 'permission_id' => 34],
            ['id' => 28, 'role_id' => 1, 'permission_id' => 35],
            ['id' => 29, 'role_id' => 1, 'permission_id' => 36],
            ['id' => 30, 'role_id' => 1, 'permission_id' => 38],
            ['id' => 31, 'role_id' => 1, 'permission_id' => 39],
            ['id' => 32, 'role_id' => 1, 'permission_id' => 40],
            ['id' => 33, 'role_id' => 1, 'permission_id' => 41],
            ['id' => 35, 'role_id' => 1, 'permission_id' => 44],
            ['id' => 36, 'role_id' => 1, 'permission_id' => 45],
            ['id' => 37, 'role_id' => 1, 'permission_id' => 46],
            ['id' => 38, 'role_id' => 1, 'permission_id' => 48],
            ['id' => 39, 'role_id' => 1, 'permission_id' => 49],
            ['id' => 40, 'role_id' => 1, 'permission_id' => 50],
            ['id' => 41, 'role_id' => 1, 'permission_id' => 52],
            ['id' => 42, 'role_id' => 1, 'permission_id' => 53],
            ['id' => 43, 'role_id' => 1, 'permission_id' => 59],
            ['id' => 44, 'role_id' => 1, 'permission_id' => 58],
            ['id' => 45, 'role_id' => 1, 'permission_id' => 60],
            ['id' => 46, 'role_id' => 1, 'permission_id' => 61],
            ['id' => 49, 'role_id' => 1, 'permission_id' => 2],
            ['id' => 50, 'role_id' => 1, 'permission_id' => 17],
            ['id' => 51, 'role_id' => 1, 'permission_id' => 7],
            ['id' => 52, 'role_id' => 1, 'permission_id' => 33],
            ['id' => 53, 'role_id' => 1, 'permission_id' => 43],
            ['id' => 54, 'role_id' => 2, 'permission_id' => 2],
            ['id' => 55, 'role_id' => 2, 'permission_id' => 3],
            ['id' => 56, 'role_id' => 2, 'permission_id' => 4],
            ['id' => 57, 'role_id' => 2, 'permission_id' => 5],
        ];

        foreach ($rolePermissions as &$rp) {
            $rp['created_at'] = $now;
            $rp['updated_at'] = $now;
        }
        unset($rp);
        RolePermission::insert($rolePermissions);

        $users = [
            [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'hieupv2412@gmail.com',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
        User::insert($users);
        User::factory(50)->create();

//        Insert data into user_campus_roles table
        $userCampusRoles = [
            ['id' => 1, 'user_id' => 1, 'campus_id' => 1, 'role_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'campus_id' => 2, 'role_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 1, 'campus_id' => 3, 'role_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'user_id' => 1, 'campus_id' => 4, 'role_id' => 1, 'created_at' => now(), 'updated_at' => now()],

        ];
        CampusUserRole::insert($userCampusRoles);

    }
}
