<?php

declare(strict_types=1);

namespace App\Services\Admissions;

use App\Models\Program;
use App\Models\StudentApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-time normalization of the legacy `intended_program` text into canonical
 * `Program.code` values (ADR-0005), so the new code-based matching resolves.
 *
 * Per row:
 *  - a **converted** Application (a linked Student exists) takes the Student's
 *    actual `Program.code` — ground truth, which correctly de-ambiguates labels
 *    like "CS" that really split across programs;
 *  - an **unconverted** Application maps its known label via {@see self::LABEL_TO_CODE};
 *  - anything else is left untouched and reported (no guessing).
 *
 * Idempotent: a row already holding its target code is a no-op, so re-running
 * reports zero further changes.
 */
class IntendedProgramNormalizer
{
    /**
     * Known legacy program labels → canonical Program code. Ambiguous labels
     * (e.g. "CS", "IT") are intentionally absent: those rows are all converted
     * and resolve from their Student instead.
     *
     * @var array<string, string>
     */
    public const LABEL_TO_CODE = [
        'Công nghệ bán dẫn' => 'SEMI',
        'Trí tuệ nhân tạo' => 'AI',
        'Tài chính' => 'FIN',
        'Quản trị kinh doanh' => 'BA',
    ];

    /**
     * @return array{applied: bool, total: int, from_student: int, from_label_map: int, unchanged: int, unmapped: int}
     */
    public function run(bool $dryRun): array
    {
        $programCodeById = Program::query()->pluck('code', 'id');

        DB::beginTransaction();

        try {
            $total = 0;
            $fromStudent = 0;
            $fromLabelMap = 0;
            $unchanged = 0;
            $unmapped = 0;

            StudentApplication::query()
                ->with(['student:id,program_id'])
                ->orderBy('id')
                ->chunkById(500, function ($applications) use (
                    &$total, &$fromStudent, &$fromLabelMap, &$unchanged, &$unmapped, $programCodeById
                ): void {
                    foreach ($applications as $application) {
                        $total++;

                        [$target, $source] = $this->targetCodeFor($application, $programCodeById);

                        if ($target === null) {
                            $unmapped++;

                            continue;
                        }

                        if ($application->intended_program === $target) {
                            $unchanged++;

                            continue;
                        }

                        DB::table('student_applications')
                            ->where('id', $application->id)
                            ->update(['intended_program' => $target]);

                        $source === 'student' ? $fromStudent++ : $fromLabelMap++;
                    }
                });

            $report = [
                'applied' => ! $dryRun,
                'total' => $total,
                'from_student' => $fromStudent,
                'from_label_map' => $fromLabelMap,
                'unchanged' => $unchanged,
                'unmapped' => $unmapped,
            ];

            $dryRun ? DB::rollBack() : DB::commit();

            return $report;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * The target program code for an Application and where it came from:
     * `[code|null, 'student'|'label'|null]`.
     *
     * @param  Collection<int, string>  $programCodeById
     * @return array{0: ?string, 1: ?string}
     */
    private function targetCodeFor(StudentApplication $application, $programCodeById): array
    {
        $student = $application->student;

        if ($student !== null && $student->program_id !== null) {
            $code = $programCodeById->get($student->program_id);

            if ($code !== null) {
                return [$code, 'student'];
            }
        }

        // Already a canonical code (e.g. a re-run, or new code-based data): keep
        // it so normalization is idempotent and code values are never disturbed.
        if ($application->intended_program !== null && $programCodeById->contains($application->intended_program)) {
            return [$application->intended_program, 'code'];
        }

        $code = self::LABEL_TO_CODE[$application->intended_program] ?? null;

        return $code !== null ? [$code, 'label'] : [null, null];
    }
}
