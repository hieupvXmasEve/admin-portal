<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
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
            ['id' => 1, 'name' => 'Super Admin', 'code' => 'super_admin'],
            ['id' => 2, 'name' => 'Giám Đốc Đào Tạo', 'code' => 'giam_doc_dao_tao'],
            ['id' => 3, 'name' => 'Trường Phòng Student HQ', 'code' => 'truong_phong_student_hq'],
            ['id' => 4, 'name' => 'Trường Phòng Hành Chính', 'code' => 'truong_phong_hanh_chinh'],
            ['id' => 5, 'name' => 'Trường Ban Tuyển Sinh', 'code' => 'truong_ban_tuyen_sinh'],
            ['id' => 6, 'name' => 'Trường Phòng Tuyển Sinh', 'code' => 'truong_phong_tuyen_sinh'],
            ['id' => 7, 'name' => 'Cán Bộ Đào Tạo', 'code' => 'can_bo_dao_tao'],
            ['id' => 8, 'name' => 'Cán Bộ Student HQ', 'code' => 'can_bo_student_hq'],
            ['id' => 9, 'name' => 'Cán Bộ Hành Chính', 'code' => 'can_bo_hanh_chinh'],
            ['id' => 10, 'name' => 'Cán Bộ Tuyển Sinh', 'code' => 'can_bo_tuyen_sinh'],
            ['id' => 11, 'name' => 'Giảng Viên', 'code' => 'giang_vien'],
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
            ['id' => 3, 'name' => 'Create User', 'parent_id' => 1, 'code' => config('permission.access.users.create_user')],
            ['id' => 4, 'name' => 'Edit User', 'parent_id' => 1, 'code' => config('permission.access.users.edit_user')],
            ['id' => 5, 'name' => 'Delete User', 'parent_id' => 1, 'code' => config('permission.access.users.delete_user')],

            ['id' => 6, 'name' => 'Room', 'parent_id' => null, 'code' => 'room'],
            ['id' => 7, 'name' => 'View Room', 'parent_id' => 6, 'code' => config('permission.access.room.view_room')],
            ['id' => 8, 'name' => 'Create Room', 'parent_id' => 6, 'code' => config('permission.access.room.create_room')],
            ['id' => 9, 'name' => 'Edit Room', 'parent_id' => 6, 'code' => config('permission.access.room.edit_room')],
            ['id' => 10, 'name' => 'Delete Room', 'parent_id' => 6, 'code' => config('permission.access.room.delete_room')],

            ['id' => 11, 'name' => 'Course', 'parent_id' => null, 'code' => 'course'],
            ['id' => 12, 'name' => 'View Course', 'parent_id' => 11, 'code' => config('permission.access.course.view_course')],
            ['id' => 13, 'name' => 'Create Course', 'parent_id' => 11, 'code' => config('permission.access.course.create_course')],
            ['id' => 14, 'name' => 'Edit Course', 'parent_id' => 11, 'code' => config('permission.access.course.edit_course')],
            ['id' => 15, 'name' => 'Delete Course', 'parent_id' => 11, 'code' => config('permission.access.course.delete_course')],

            ['id' => 16, 'name' => 'Semesters', 'parent_id' => null, 'code' => 'semesters'],
            ['id' => 17, 'name' => 'View Semesters', 'parent_id' => 16, 'code' => config('permission.access.semesters.view_semester')],
            ['id' => 18, 'name' => 'Create Semesters', 'parent_id' => 16, 'code' => config('permission.access.semesters.create_semester')],
            ['id' => 19, 'name' => 'Edit Semesters', 'parent_id' => 16, 'code' => config('permission.access.semesters.edit_semester')],
            ['id' => 20, 'name' => 'Delete Semesters', 'parent_id' => 16, 'code' => config('permission.access.semesters.delete_semester')],

            ['id' => 21, 'name' => 'Groups', 'parent_id' => null, 'code' => 'groups'],
            ['id' => 22, 'name' => 'View Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.view_groups')],
            ['id' => 23, 'name' => 'Create Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.create_groups')],
            ['id' => 24, 'name' => 'Create Student Group', 'parent_id' => 21, 'code' => config('permission.access.groups.create_student_group')],
            ['id' => 25, 'name' => 'Edit Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.edit_groups')],
            ['id' => 26, 'name' => 'Delete Groups', 'parent_id' => 21, 'code' => config('permission.access.groups.delete_groups')],

            ['id' => 27, 'name' => 'Events', 'parent_id' => null, 'code' => 'events'],
            ['id' => 28, 'name' => 'View Events', 'parent_id' => 27, 'code' => config('permission.access.events.view_events')],
            ['id' => 29, 'name' => 'Create Events', 'parent_id' => 27, 'code' => config('permission.access.events.create_events')],
            ['id' => 30, 'name' => 'Edit Events', 'parent_id' => 27, 'code' => config('permission.access.events.edit_events')],
            ['id' => 31, 'name' => 'Delete Events', 'parent_id' => 27, 'code' => config('permission.access.events.delete_events')],

            ['id' => 32, 'name' => 'Clubs', 'parent_id' => null, 'code' => 'clubs'],
            ['id' => 33, 'name' => 'View Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.view_clubs')],
            ['id' => 34, 'name' => 'Create Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.create_clubs')],
            ['id' => 35, 'name' => 'Edit Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.edit_clubs')],
            ['id' => 36, 'name' => 'Delete Clubs', 'parent_id' => 32, 'code' => config('permission.access.clubs.delete_clubs')],

            ['id' => 37, 'name' => 'Items', 'parent_id' => null, 'code' => 'items'],
            ['id' => 38, 'name' => 'View Items', 'parent_id' => 37, 'code' => config('permission.access.items.view_items')],
            ['id' => 39, 'name' => 'Create Items', 'parent_id' => 37, 'code' => config('permission.access.items.create_items')],
            ['id' => 40, 'name' => 'Edit Items', 'parent_id' => 37, 'code' => config('permission.access.items.edit_items')],
            ['id' => 41, 'name' => 'Delete Items', 'parent_id' => 37, 'code' => config('permission.access.items.delete_items')],

            ['id' => 42, 'name' => 'Fees', 'parent_id' => null, 'code' => 'fees'],
            ['id' => 43, 'name' => 'View Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.view_fees')],
            ['id' => 44, 'name' => 'Create Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.create_fees')],
            ['id' => 45, 'name' => 'Import Fees', 'parent_id' => 42, 'code' => config('permission.access.fees.import_fees')],

            ['id' => 46, 'name' => 'Queries', 'parent_id' => null, 'code' => 'queries'],
            ['id' => 47, 'name' => 'View Queries', 'parent_id' => 46, 'code' => config('permission.access.queries.view_queries')],
            ['id' => 48, 'name' => 'Detail Queries', 'parent_id' => 46, 'code' => config('permission.access.queries.detail_queries')],

            ['id' => 49, 'name' => 'Golds', 'parent_id' => null, 'code' => 'golds'],
            ['id' => 50, 'name' => 'View Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.view_golds')],
            ['id' => 51, 'name' => 'Create Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.create_golds')],
            ['id' => 52, 'name' => 'Edit Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.edit_golds')],
            ['id' => 53, 'name' => 'Detail Golds', 'parent_id' => 49, 'code' => config('permission.access.golds.detail_golds')],

            ['id' => 54, 'name' => 'Role Management', 'parent_id' => null, 'code' => 'role_management'],
            ['id' => 55, 'name' => 'View Role', 'parent_id' => 54, 'code' => config('permission.access.roles.view_role')],
            ['id' => 56, 'name' => 'Create Role', 'parent_id' => 54, 'code' => config('permission.access.roles.create_role')],
            ['id' => 57, 'name' => 'Edit Role', 'parent_id' => 54, 'code' => config('permission.access.roles.edit_role')],
            ['id' => 58, 'name' => 'Delete Role', 'parent_id' => 54, 'code' => config('permission.access.roles.delete_role')],

            ['id' => 59, 'name' => 'Permission Management', 'parent_id' => null, 'code' => 'permission_management'],
            ['id' => 60, 'name' => 'View Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.view_permission')],
            ['id' => 61, 'name' => 'Create Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.create_permission')],
            ['id' => 62, 'name' => 'Edit Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.edit_permission')],
            ['id' => 63, 'name' => 'Delete Permission', 'parent_id' => 59, 'code' => config('permission.access.permissions.delete_permission')],

            ['id' => 64, 'name' => 'Campus Management', 'parent_id' => null, 'code' => 'campus_management'],
            ['id' => 65, 'name' => 'View Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.view_campus')],
            ['id' => 66, 'name' => 'Create Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.create_campus')],
            ['id' => 67, 'name' => 'Edit Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.edit_campus')],
            ['id' => 68, 'name' => 'Delete Campus', 'parent_id' => 64, 'code' => config('permission.access.campuses.delete_campus')],

            ['id' => 69, 'name' => 'Import User', 'parent_id' => 1, 'code' => config('permission.access.users.import_user')],
            ['id' => 70, 'name' => 'Export User', 'parent_id' => 1, 'code' => config('permission.access.users.export_user')],

            ['id' => 71, 'name' => 'Units', 'parent_id' => null, 'code' => 'units'],
            ['id' => 72, 'name' => 'View Units', 'parent_id' => 71, 'code' => config('permission.access.units.view_unit')],
            ['id' => 73, 'name' => 'Create Units', 'parent_id' => 71, 'code' => config('permission.access.units.create_unit')],
            ['id' => 74, 'name' => 'Edit Units', 'parent_id' => 71, 'code' => config('permission.access.units.edit_unit')],
            ['id' => 75, 'name' => 'Delete Units', 'parent_id' => 71, 'code' => config('permission.access.units.delete_unit')],

            ['id' => 76, 'name' => 'Programs', 'parent_id' => null, 'code' => 'programs'],
            ['id' => 77, 'name' => 'View Programs', 'parent_id' => 76, 'code' => config('permission.access.programs.view_program')],
            ['id' => 78, 'name' => 'Create Programs', 'parent_id' => 76, 'code' => config('permission.access.programs.create_program')],
            ['id' => 79, 'name' => 'Edit Programs', 'parent_id' => 76, 'code' => config('permission.access.programs.edit_program')],
            ['id' => 80, 'name' => 'Delete Programs', 'parent_id' => 76, 'code' => config('permission.access.programs.delete_program')],

            ['id' => 81, 'name' => 'Curriculum Versions', 'parent_id' => null, 'code' => 'curriculum_versions'],
            ['id' => 82, 'name' => 'View Curriculum Versions', 'parent_id' => 81, 'code' => config('permission.access.curriculum_versions.view_curriculum_version')],
            ['id' => 83, 'name' => 'Create Curriculum Versions', 'parent_id' => 81, 'code' => config('permission.access.curriculum_versions.create_curriculum_version')],
            ['id' => 84, 'name' => 'Edit Curriculum Versions', 'parent_id' => 81, 'code' => config('permission.access.curriculum_versions.edit_curriculum_version')],
            ['id' => 85, 'name' => 'Delete Curriculum Versions', 'parent_id' => 81, 'code' => config('permission.access.curriculum_versions.delete_curriculum_version')],

            ['id' => 86, 'name' => 'Curriculum Units', 'parent_id' => null, 'code' => 'curriculum_units'],
            ['id' => 87, 'name' => 'View Curriculum Units', 'parent_id' => 86, 'code' => config('permission.access.curriculum_units.view_curriculum_unit')],
            ['id' => 88, 'name' => 'Create Curriculum Units', 'parent_id' => 86, 'code' => config('permission.access.curriculum_units.create_curriculum_unit')],
            ['id' => 89, 'name' => 'Edit Curriculum Units', 'parent_id' => 86, 'code' => config('permission.access.curriculum_units.edit_curriculum_unit')],
            ['id' => 90, 'name' => 'Delete Curriculum Units', 'parent_id' => 86, 'code' => config('permission.access.curriculum_units.delete_curriculum_unit')],
        ];

        // foreach ($permissions as &$permission) {
        //     $permission['created_at'] = $now;
        //     $permission['updated_at'] = $now;
        // }

        // unset($permission);

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

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

            ['role_id' => 1, 'permission_id' => 69],
            ['role_id' => 1, 'permission_id' => 70],

            ['role_id' => 1, 'permission_id' => 72],
            ['role_id' => 1, 'permission_id' => 73],
            ['role_id' => 1, 'permission_id' => 74],
            ['role_id' => 1, 'permission_id' => 75],

            ['role_id' => 1, 'permission_id' => 77],
            ['role_id' => 1, 'permission_id' => 78],
            ['role_id' => 1, 'permission_id' => 79],
            ['role_id' => 1, 'permission_id' => 80],

            ['role_id' => 1, 'permission_id' => 82],
            ['role_id' => 1, 'permission_id' => 83],
            ['role_id' => 1, 'permission_id' => 84],
            ['role_id' => 1, 'permission_id' => 85],

            ['role_id' => 1, 'permission_id' => 87],
            ['role_id' => 1, 'permission_id' => 88],
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
