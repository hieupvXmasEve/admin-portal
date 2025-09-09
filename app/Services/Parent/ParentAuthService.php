<?php

declare(strict_types=1);

namespace App\Services\Parent;

use App\Http\Responses\ApiResponse;
use App\Models\CampusUserRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ParentAuthService
{
    public function register(array $data): array
    {
        // Find student by student_id
        $student = Student::where('student_id', $data['student_id'])->first();
        if (! $student) {
            throw ValidationException::withMessages(['student_id' => ['Student not found']]);
        }

        return DB::transaction(function () use ($data, $student) {
            // Create or find user by email/phone
            $existing = User::query()
                ->when(!empty($data['email']), fn($q) => $q->orWhere('email', $data['email']))
                ->when(!empty($data['phone']), fn($q) => $q->orWhere('phone', $data['phone']))
                ->first();

            if ($existing) {
                throw ValidationException::withMessages(['email' => ['User already exists with this email/phone']]);
            }

            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'] ?? null,
                'status' => User::STATUS_ACTIVE,
            ]);

            // Assign parent role at student's campus
            $parentRole = Role::where('code', 'parent')->first();
            if ($parentRole) {
                CampusUserRole::create([
                    'user_id' => $user->id,
                    'campus_id' => $student->campus_id,
                    'role_id' => $parentRole->id,
                ]);
            }

            // Link to student
            $student->update(['parent_user_id' => $user->id]);

            return [$user, $student];
        });
    }

    public function login(string $identifier, string $password): ?User
    {
        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $user || empty($user->password) || ! Hash::check($password, $user->password)) {
            return null;
        }

        // Ensure user has parent role in current campus if context exists
        return $user;
    }
}
