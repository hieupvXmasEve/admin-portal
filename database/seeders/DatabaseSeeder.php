<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Tạo 5 campus demo
        \App\Models\Campus::factory(5)->create();

        // Tạo 10 permission demo
        \App\Models\Permission::factory(10)->create();

        // Tạo 5 role demo
        \App\Models\Role::factory(5)->create();

        // Tạo 20 user demo
        \App\Models\User::factory(20)->create();

        // Gán mỗi user vào 1-2 campus với 1 role ngẫu nhiên
        $users = \App\Models\User::all();
        $campuses = \App\Models\Campus::all();
        $roles = \App\Models\Role::all();
        foreach ($users as $user) {
            $campusSample = $campuses->random(rand(1, 2));
            foreach ($campusSample as $campus) {
                $role = $roles->random();
                DB::table('user_campus_roles')->insert([
                    'user_id' => $user->id,
                    'campus_id' => $campus->id,
                    'role_id' => $role->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Gán mỗi role 2-4 permission ngẫu nhiên
        $permissions = \App\Models\Permission::all();
        foreach ($roles as $role) {
            $permSample = $permissions->random(rand(2, 4));
            $role->permissions()->syncWithoutDetaching($permSample->pluck('id')->toArray());
        }
    }
}
