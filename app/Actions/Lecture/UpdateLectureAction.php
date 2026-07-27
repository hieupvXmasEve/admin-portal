<?php

declare(strict_types=1);

namespace App\Actions\Lecture;

use App\Models\Lecture;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UpdateLectureAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Lecture $lecture, array $validated): void
    {
        DB::transaction(function () use ($validated, $lecture): void {
            $password = $validated['password'] ?? null;
            unset($validated['password']);

            $user = $lecture->user;

            if (! $user) {
                $user = User::create([
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($password ?? Str::random(16)),
                    'type' => UserType::LECTURER,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $validated['user_id'] = $user->id;
            } else {
                $user->update([
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? $user->phone,
                    'type' => UserType::LECTURER,
                ]);

                if ($password !== null) {
                    $user->update(['password' => Hash::make($password)]);
                }
            }

            $lecture->update($validated);
        });
    }
}
