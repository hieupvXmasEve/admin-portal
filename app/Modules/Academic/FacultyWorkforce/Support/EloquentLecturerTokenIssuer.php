<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;

final class EloquentLecturerTokenIssuer implements LecturerTokenIssuer
{
    public function issue(int $lecturerId, string $deviceName, ?string $avatarUrl = null): array
    {
        $lecturer = Lecture::query()->find($lecturerId);

        if ($lecturer === null) {
            throw new \DomainException('This account is not associated with a lecturer profile.');
        }

        if ($lecturer->avatar_url === null && $avatarUrl !== null) {
            $lecturer->update(['avatar_url' => $avatarUrl]);
        }

        return [
            'token' => $lecturer->createToken(
                $deviceName,
                ['lecturer:access'],
                now()->addHours(8),
            )->plainTextToken,
            'lecturer' => $lecturer,
        ];
    }
}
