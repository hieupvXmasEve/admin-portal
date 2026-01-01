<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add 'type' to users
        if (!Schema::hasColumn('users', 'type')) {
            Schema::table('users', function (Blueprint $table) {
                // Default to 'staff' for existing users in the 'users' table
                $table->enum('type', \App\Shared\Support\Enums\UserType::values())->default(\App\Shared\Support\Enums\UserType::STAFF->value)->after('email');
            });
        }

        // 2. Add user_id to students
        if (!Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        // 3. Add user_id to lectures
        if (!Schema::hasColumn('lectures', 'user_id')) {
            Schema::table('lectures', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        // 4. Data Migration
        DB::transaction(function () {
            // Migrate Students
            $students = DB::table('students')->get();
            foreach ($students as $student) {
                if (empty($student->email)) continue;

                $user = DB::table('users')->where('email', $student->email)->first();
                
                if (!$user) {
                    // Check if password exists, otherwise generate random
                    $password = isset($student->password) ? $student->password : Hash::make(Str::random(16));
                    
                    $userId = DB::table('users')->insertGetId([
                        'name' => $student->full_name ?? 'Student',
                        'email' => $student->email,
                        'password' => $password,
                        'type' => 'student',
                        'status' => 'active', 
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $userId = $user->id;
                    // If user exists, we leave them as is (likely 'staff' or already migrated)
                }

                DB::table('students')->where('id', $student->id)->update(['user_id' => $userId]);
            }

            // Migrate Lecturers
            $lecturers = DB::table('lectures')->get();
            foreach ($lecturers as $lecture) {
                if (empty($lecture->email)) continue;
                
                $user = DB::table('users')->where('email', $lecture->email)->first();
                
                if (!$user) {
                    $name = trim(($lecture->first_name ?? '') . ' ' . ($lecture->last_name ?? ''));
                    if (empty($name)) $name = 'Lecturer';

                    $password = isset($lecture->password) ? $lecture->password : Hash::make(Str::random(16));

                    $userId = DB::table('users')->insertGetId([
                        'name' => $name,
                        'email' => $lecture->email,
                        'password' => $password,
                        'type' => 'lecturer',
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $userId = $user->id;
                }
                
                DB::table('lectures')->where('id', $lecture->id)->update(['user_id' => $userId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('lectures', 'user_id')) {
            Schema::table('lectures', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (Schema::hasColumn('users', 'type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
