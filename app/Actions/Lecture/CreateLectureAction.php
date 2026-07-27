<?php

declare(strict_types=1);

namespace App\Actions\Lecture;

use App\Models\Lecture;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateLectureAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated): Lecture
    {
        return DB::transaction(function () use ($validated): Lecture {
            $password = $validated['password'] ?? null;
            unset($validated['password']);

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($password ?? Str::random(16)),
                    'type' => UserType::LECTURER,
                    'status' => User::STATUS_ACTIVE,
                ]
            );

            if ($user->type !== UserType::LECTURER) {
                $user->update(['type' => UserType::LECTURER]);
            }

            $user->update([
                'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                'phone' => $validated['phone'] ?? $user->phone,
            ]);

            if ($password !== null) {
                $user->update(['password' => Hash::make($password)]);
            }

            $validated['user_id'] = $user->id;

            return Lecture::create($validated);
        });
    }
}
