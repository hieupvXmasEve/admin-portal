<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentProfileWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class UploadStudentPortalAvatarAction
{
    /**
     * @param  array{student_id: int, file: UploadedFile}  $data
     * @return array{avatar_path: string, avatar_url: string}
     */
    public static function run(array $data): array
    {
        $studentId = $data['student_id'];
        $file = $data['file'];
        $profile = app(StudentProfileReader::class)->findProfile($studentId);
        if ($profile?->avatarUrl !== null) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $profile->avatarUrl);
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $file->store('avatars', 'public');
        $avatarUrl = Storage::disk('public')->url($path);
        app(StudentProfileWriter::class)->update($studentId, ['avatar_url' => $avatarUrl]);
        self::clearCache($studentId);

        return ['avatar_path' => $path, 'avatar_url' => $avatarUrl];
    }

    public static function clearCache(int $studentId): void
    {
        foreach (["profile:student:{$studentId}", "study_plan:student:{$studentId}", "academic_history:student:{$studentId}"] as $key) {
            Cache::forget($key);
        }
    }
}
