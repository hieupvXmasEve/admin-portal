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
            ['id' => 2, 'name' => 'View User', 'parent_id' => 1, 'code' => config('permission.access.users.view_user')],
            ['id' => 3, 'name' => 'Add User', 'parent_id' => 1, 'code' => config('permission.access.users.add_user')],
            ['id' => 4, 'name' => 'Edit User', 'parent_id' => 1, 'code' => config('permission.access.users.edit_user')],
            ['id' => 5, 'name' => 'Delete User', 'parent_id' => 1, 'code' => config('permission.access.users.delete_user')],

            ['id' => 6, 'name' => 'Room', 'parent_id' => null, 'code' => 'room'],
            ['id' => 7, 'name' => 'View Room', 'parent_id' => 6, 'code' => config('permission.access.room.view_room')],
            ['id' => 8, 'name' => 'Add Room', 'parent_id' => 6, 'code' => config('permission.access.room.add_room')],
            ['id' => 9, 'name' => 'Edit Room', 'parent_id' => 6, 'code' => config('permission.access.room.edit_room')],
            ['id' => 10, 'name' => 'Delete Room', 'parent_id' => 6, 'code' => config('permission.access.room.delete_room')],

            ['id' => 11, 'name' => 'Course', 'parent_id' => null, 'code' => 'course'],
            ['id' => 12, 'name' => 'View Course', 'parent_id' => 11, 'code' => config('permission.access.course.view_course')],
            ['id' => 13, 'name' => 'Add Course', 'parent_id' => 11, 'code' => config('permission.access.course.add_course')],
            ['id' => 14, 'name' => 'Edit Course', 'parent_id' => 11, 'code' => config('permission.access.course.edit_course')],
            ['id' => 15, 'name' => 'Delete Course', 'parent_id' => 11, 'code' => config('permission.access.course.delete_course')],

            ['id' => 16, 'name' => 'Terms', 'parent_id' => null, 'code' => 'terms'],
            ['id' => 17, 'name' => 'View Terms', 'parent_id' => 16, 'code' => config('permission.access.terms.view_terms')],
            ['id' => 18, 'name' => 'Add Terms', 'parent_id' => 16, 'code' => config('permission.access.terms.add_terms')],
            ['id' => 19, 'name' => 'Edit Terms', 'parent_id' => 16, 'code' => config('permission.access.terms.edit_terms')],
            ['id' => 20, 'name' => 'Delete Terms', 'parent_id' => 16, 'code' => config('permission.access.terms.delete_terms')],

            ['id' => 21, 'name' => 'Groups', 'parent_id' => null, 'code' => 'groups'],
            ['id' => 22, 'name' => 'View Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.view_groups')],
            ['id' => 23, 'name' => 'Add Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.add_groups')],
            ['id' => 24, 'name' => 'Add Student Group', 'parent_id' => 21, 'code' => config('permission.access.groups.add_student_group')],
            ['id' => 25, 'name' => 'Edit Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.edit_groups')],
            ['id' => 26, 'name' => 'Delete Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.delete_groups')],

            ['id' => 27, 'name' => 'Events', 'parent_id' => null, 'code' => 'events'],
            ['id' => 28, 'name' => 'View Events', 'parent_id' => 27, 'code' => config('permission.access.events.view_events')],
            ['id' => 29, 'name' => 'Add Events', 'parent_id' => 27, 'code' => config('permission.access.events.add_events')],
            ['id' => 30, 'name' => 'Edit Events', 'parent_id' => 27, 'code' => config('permission.access.events.edit_events')],
            ['id' => 31, 'name' => 'Delete Events', 'parent_id' => 27, 'code' => config('permission.access.events.delete_events')],

            ['id' => 32, 'name' => 'Clubs', 'parent_id' => null, 'code' => 'clubs'],
            ['id' => 33, 'name' => 'View Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.view_clubs')],
            ['id' => 34, 'name' => 'Add Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.add_clubs')],
            ['id' => 35, 'name' => 'Edit Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.edit_clubs')],
            ['id' => 36, 'name' => 'Delete Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.delete_clubs')],

            ['id' => 37, 'name' => 'Items', 'parent_id' => null, 'code' => 'items'],
            ['id' => 38, 'name' => 'View Items', 'parent_id' => 37, 'code' => config('permission.access.items.view_items')],
            ['id' => 39, 'name' => 'Add Items', 'parent_id' => 37, 'code' => config('permission.access.items.add_items')],
            ['id' => 40, 'name' => 'Edit Items', 'parent_id' => 37, 'code' => config('permission.access.items.edit_items')],
            ['id' => 41, 'name' => 'Delete Items', 'parent_id' => 37, 'code' => config('permission.access.items.delete_items')],

            ['id' => 42, 'name' => 'Fees', 'parent_id' => null, 'code' => 'fees'],
            ['id' => 43, 'name' => 'View Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.view_fees')],
            ['id' => 44, 'name' => 'Add Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.add_fees')],
            ['id' => 45, 'name' => 'Import Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.import_fees')],

            ['id' => 46, 'name' => 'Queries', 'parent_id' => null, 'code' => 'queries'],
            ['id' => 47, 'name' => 'View Queries', 'parent_id' => 46, 'code' => config('permission.access.queries.view_queries')],
            ['id' => 48, 'name' => 'Detail Queries', 'parent_id' => 46, 'code' => config('permission.access.queries.detail_queries')],

            ['id' => 49, 'name' => 'Golds', 'parent_id' => null, 'code' => 'golds'],
            ['id' => 50, 'name' => 'View Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.view_golds')],
            ['id' => 51, 'name' => 'Add Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.add_golds')],
            ['id' => 52, 'name' => 'Edit Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.edit_golds')],
            ['id' => 53, 'name' => 'Detail Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.detail_golds')],

            ['id' => 54, 'name' => 'Role Management', 'parent_id' => null, 'code' => 'role_management'],
            ['id' => 55, 'name' => 'View Role', 'parent_id' => 54, 'code' => config('permission.access.roles.view_role')],
            ['id' => 56, 'name' => 'Add Role', 'parent_id' => 54, 'code' => config('permission.access.roles.add_role')],
            ['id' => 57, 'name' => 'Edit Role', 'parent_id' => 54, 'code' => config('permission.access.roles.edit_role')],
            ['id' => 58, 'name' => 'Delete Role', 'parent_id' => 54, 'code' => config('permission.access.roles.delete_role')],

            ['id' => 59, 'name' => 'Permission Management', 'parent_id' => null, 'code' => 'permission_management'],
            ['id' => 60, 'name' => 'View Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.view_permission')],
            ['id' => 61, 'name' => 'Add Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.add_permission')],
            ['id' => 62, 'name' => 'Edit Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.edit_permission')],
            ['id' => 63, 'name' => 'Delete Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.delete_permission')],

            ['id' => 64, 'name' => 'Campus Management', 'parent_id' => null, 'code' => 'campus_management'],
            ['id' => 65, 'name' => 'View Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.view_campus')],
            ['id' => 66, 'name' => 'Add Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.add_campus')],
            ['id' => 67, 'name' => 'Edit Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.edit_campus')],
            ['id' => 68, 'name' => 'Delete Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.delete_campus')],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }

        unset($permission);

        Permission::insert($permissions);
        $rolePermissions = [
            ['role_id' => 1, 'permission_id' => 2],
            ['role_id' => 1, 'permission_id' => 3],
            ['role_id' => 1, 'permission_id' => 4],
            ['role_id' => 1, 'permission_id' => 5],

            ['role_id' => 1, 'permission_id' => 7],
            ['role_id' => 1, 'permission_id' => 8],
            ['role_id' => 1, 'permission_id' => 9],
            ['role_id' => 1, 'permission_id' => 10],

            ['role_id' => 1, 'permission_id' => 12],
            ['role_id' => 1, 'permission_id' => 13],
            ['role_id' => 1, 'permission_id' => 14],
            ['role_id' => 1, 'permission_id' => 15],

            ['role_id' => 1, 'permission_id' => 17],
            ['role_id' => 1, 'permission_id' => 18],
            ['role_id' => 1, 'permission_id' => 19],
            ['role_id' => 1, 'permission_id' => 20],

            ['role_id' => 1, 'permission_id' => 22],
            ['role_id' => 1, 'permission_id' => 23],
            ['role_id' => 1, 'permission_id' => 24],
            ['role_id' => 1, 'permission_id' => 25],
            ['role_id' => 1, 'permission_id' => 26],

            ['role_id' => 1, 'permission_id' => 28],
            ['role_id' => 1, 'permission_id' => 29],
            ['role_id' => 1, 'permission_id' => 30],
            ['role_id' => 1, 'permission_id' => 31],

            ['role_id' => 1, 'permission_id' => 33],
            ['role_id' => 1, 'permission_id' => 34],
            ['role_id' => 1, 'permission_id' => 35],
            ['role_id' => 1, 'permission_id' => 36],

            ['role_id' => 1, 'permission_id' => 38],
            ['role_id' => 1, 'permission_id' => 39],
            ['role_id' => 1, 'permission_id' => 40],
            ['role_id' => 1, 'permission_id' => 41],

            ['role_id' => 1, 'permission_id' => 43],
            ['role_id' => 1, 'permission_id' => 44],
            ['role_id' => 1, 'permission_id' => 45],

            ['role_id' => 1, 'permission_id' => 47],
            ['role_id' => 1, 'permission_id' => 48],

            ['role_id' => 1, 'permission_id' => 50],
            ['role_id' => 1, 'permission_id' => 51],
            ['role_id' => 1, 'permission_id' => 52],
            ['role_id' => 1, 'permission_id' => 53],

            ['role_id' => 1, 'permission_id' => 55],
            ['role_id' => 1, 'permission_id' => 56],
            ['role_id' => 1, 'permission_id' => 57],
            ['role_id' => 1, 'permission_id' => 58],

            ['role_id' => 1, 'permission_id' => 60],
            ['role_id' => 1, 'permission_id' => 61],
            ['role_id' => 1, 'permission_id' => 62],
            ['role_id' => 1, 'permission_id' => 63],

            ['role_id' => 1, 'permission_id' => 65],
            ['role_id' => 1, 'permission_id' => 66],
            ['role_id' => 1, 'permission_id' => 67],
            ['role_id' => 1, 'permission_id' => 68],
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
        User::factory(200)->create();

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
