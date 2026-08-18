<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Models\Student;
use App\Modules\StudentRegistry\Exceptions\StudentDeletionBlockedException;
use App\Shared\Contracts\Finance\StudentDeletionSettlementSnapshotReader;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use Illuminate\Support\Facades\DB;

final class SoftDeleteStudentAction
{
    public static function run(Student $student, int $actingCampusId): void
    {
        if ((int) $student->campus_id !== $actingCampusId) {
            abort(404);
        }

        DB::transaction(function () use ($student): void {
            $snapshot = app(StudentDeletionSettlementSnapshotReader::class)->guardSnapshotFor((int) $student->id);

            if (! $snapshot['valid']) {
                throw new StudentDeletionBlockedException(
                    'Student has no billing account on record, or their finance position could not be verified, and cannot be deleted until finance data is confirmed clean.'
                );
            }

            if ((float) $snapshot['remaining'] !== 0.0) {
                throw new StudentDeletionBlockedException(
                    'Student has a non-zero finance balance and cannot be deleted.'
                );
            }

            if ((float) $snapshot['unapplied'] !== 0.0) {
                throw new StudentDeletionBlockedException(
                    'Student has an unapplied cash surplus (owed a refund) and cannot be deleted.'
                );
            }

            $student->tokens()->delete();

            if ($student->user_id !== null) {
                app(StudentAccessWriter::class)->revoke((int) $student->user_id);
            }

            // student_id/national_id are VARCHAR(20) — a real value can already
            // use most of that width, so there is no room to prefix the
            // original value without risking truncation. Those two get a
            // purely synthetic, PK-based tombstone (unique via the primary
            // key) instead of a preserved-original prefix. email is
            // VARCHAR(255) and keeps the original value for context.
            $student->forceFill([
                'email' => 'deleted-'.now()->timestamp.'-'.$student->email,
                'student_id' => 'del-'.$student->id,
                'national_id' => $student->national_id === null ? null : 'del-'.$student->id,
            ])->save();

            $student->delete();
        });
    }
}
