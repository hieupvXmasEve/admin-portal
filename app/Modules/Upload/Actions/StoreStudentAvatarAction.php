<?php

declare(strict_types=1);

namespace App\Modules\Upload\Actions;

use App\Models\UploadRecord;
use App\Modules\Upload\Support\UploadPlatform;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;

class StoreStudentAvatarAction
{
    public function __construct(
        private readonly UploadPlatform $uploads,
        private readonly StudentReferenceReader $students,
    ) {}

    public function handle(
        int $studentId,
        UploadedFile $file,
        ?int $userId,
    ): UploadRecord {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw (new ModelNotFoundException)->setModel('Student', [$studentId]);
        }

        $customFilename = sprintf(
            'student_%s_%s.%s',
            $student->studentCode,
            now()->format('Y-m-d_H-i-s'),
            $file->getClientOriginalExtension(),
        );

        return $this->uploads->uploadWithFilename(
            $file,
            'avatar',
            $customFilename,
            $userId,
            $student->id,
            [
                'student_id' => $student->studentCode,
                'description' => 'Student profile avatar',
                'uploaded_by' => $userId,
            ],
        );
    }
}
