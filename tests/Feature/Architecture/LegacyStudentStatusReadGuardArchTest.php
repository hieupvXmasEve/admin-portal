<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * Guards `students.status` (write-dead, plan 260817-0017) from creeping back
 * into a code path that wants *current* lifecycle state. The repo has no CI
 * running tests, so this is the only mechanical barrier — and only if
 * someone runs it.
 *
 * Receiver-anchored, not type-inferred (ponytail, phase 5): a file is a
 * candidate only if it imports `App\Models\Student`, or is `Student.php` /
 * `StudentAuditableModel.php` themselves (self-referential, need no import).
 * A blanket "any file under app/Models/" was tried first and rejected —
 * every other model has its own unrelated `status` column (`AcademicHold`,
 * `BillingCycle`, `User`, …) and got flagged on it. A `StudentReference` DTO
 * read (the migration's *success* — the 5 Delivery gates read it after
 * phase 1 flipped the source) is excluded because those files never import
 * the Student model. This is heuristic: a file importing both the model and
 * the DTO would be flagged on a DTO read. Accepted — upgrade only if it
 * actually misfires.
 */
function legacyStatusGuardStripComments(string $code): string
{
    $tokens = token_get_all($code);
    $stripped = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $stripped .= $token[1];
        } else {
            $stripped .= $token;
        }
    }

    return $stripped;
}

/**
 * `app/Models/Student.php` and `StudentAuditableModel.php` (Student's own
 * base class) are self-referential candidates: they never need to `use`
 * themselves. Every other file's candidacy comes from the import. Blanket
 * "any file under app/Models/" was tried and rejected — every other model
 * has its own unrelated `status` column (`AcademicHold`, `BillingCycle`,
 * `User`, …), and `app/Models/` blanket candidacy plus a bare `->status`
 * match flagged dozens of files that have nothing to do with `Student`.
 */
function legacyStatusGuardIsCandidate(string $relativePath, string $contents): bool
{
    $selfReferential = ['Models/Student.php', 'Models/StudentAuditableModel.php'];

    return in_array($relativePath, $selfReferential, true)
        || preg_match('/^use\s+App\\\\Models\\\\Student;/m', $contents) === 1;
}

/**
 * Every pattern is anchored on an identifier containing "student" (variable,
 * relation name, or the `Student::`/`students.` receiver) — a bare
 * `->status` or `where('status', …)` also matches every *other* model's own
 * unrelated status column once a file is a candidate for any reason.
 */
function legacyStatusGuardHasRead(string $strippedCode, string $relativePath): bool
{
    $patterns = [
        '/\$student\??->status\b/i',                                  // $student->status / $student?->status
        '/->student\??->status\b/i',                                  // $this->student->status, $request->student->status
        '/\bstudents\.status\b/',                                     // raw SQL column
        '/Student::where\(\s*[\'"]status[\'"]/',                      // Student::where('status', ...) without ::query()
        '/whereHas\(\s*[\'"]student[\'"],[\s\S]{0,120}?where(?:In|NotIn)?\(\s*[\'"]status[\'"]/', // whereHas('student', fn($q) => $q->where('status', ...)) — tight window so a where('status', ...) on a *different*, later scope in the same class doesn't false-positive
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $strippedCode) === 1) {
            return true;
        }
    }

    // `$this->status` unambiguously means `students.status` only inside
    // Student.php/StudentAuditableModel.php themselves — everywhere else
    // `$this` refers to a different class with its own unrelated column.
    $selfReferential = ['Models/Student.php', 'Models/StudentAuditableModel.php'];
    if (in_array($relativePath, $selfReferential, true) && preg_match('/\$this->status\b/', $strippedCode) === 1) {
        return true;
    }

    return legacyStatusGuardHasBareStatusShapeNearStudentQuery($strippedCode);
}

/**
 * `whereIn`/`whereNotIn`/`pluck`/`groupBy`/`select` on a bare `'status'`
 * string carry no "student" identifier of their own — they only mean
 * `students.status` when *directly chained* off `Student::query()` /
 * `Student::where(`. A tight window (not "anywhere in the same function")
 * because `Student::query()->find(...)` to resolve one student, followed
 * much later in the same method by an unrelated `whereIn('status', …)` on a
 * completely different table (`ScholarshipSemesterAdjustment`,
 * `ExamResitAttempt`, a payment-statuses constant, …), is a common shape
 * that a same-function check alone cannot distinguish from a real chain.
 */
function legacyStatusGuardHasBareStatusShapeNearStudentQuery(string $strippedCode): bool
{
    return preg_match(
        '/Student::(?:query\(\)|where\()[\s\S]{0,600}?(?:whereIn|whereNotIn|pluck|groupBy|select)\(\s*[\'"]status[\'"]/',
        $strippedCode,
    ) === 1;
}

/**
 * Phase 0's classification table (plan.md, "A1 Classification Table"),
 * buckets F (whitelist-fallback), H (whitelist-historical), N
 * (whitelist-no-edit) — every entry here is a file that still imports
 * `App\Models\Student` (or lives in app/Models/) AND still contains a
 * matching read after phases 1-4 landed. Files in those buckets that don't
 * import the Student model directly (e.g. they only touch a relation
 * property, or already resolve through a DTO) are excluded from candidacy
 * entirely and need no entry here.
 *
 * @return list<string>
 */
function legacyStatusGuardWhitelist(): array
{
    return [
        // F — is the fallback mechanism itself
        'Modules/Academic/Progression/Support/StudentLifecycleStatusReader.php',
        // F — already enrollment-first (advisory Q1, resolved); read-only
        'Modules/StudentRegistry/Support/EloquentStudentCollectionEligibilityReader.php',
        // F — overwrites the DTO field with the live value; fallback tail
        'Modules/StudentRegistry/Queries/ListStudentsQuery.php',
        // F — fallback tail
        'Modules/StudentRegistry/Http/Web/StudentController.php',
        // F — `resolveOne()`/`resolveMany()` unpersisted-guard and fallback tails; the ~20-consumer flip point
        'Modules/StudentRegistry/Support/EloquentStudentRegistryStore.php',
        // F — fallback tail on the API profile/register/me endpoints
        'Http/Controllers/Api/AuthController.php',
        'Http/Controllers/Api/StudentController.php',
        // F — fallback tail (phase 4 additions beyond the plan's original file list)
        'Console/Commands/CreateEgcStudentActionLogsCommand.php',
        'Modules/Academic/Progression/Queries/PreviewStudentDecisionBulkLinkQuery.php',
        'Modules/Academic/Support/AiAcademicEntitySearchReader.php',
        'Modules/Academic/Support/AiAcademicStudentProfileReader.php',
        'Modules/StudentRegistry/Support/EloquentStudentImpersonationTokenIssuer.php',
        'Modules/StudentRegistry/Support/EloquentStudentPortalProfileReader.php',
        // F — unpersisted-guard branch, same shape as the reader's own fallback (H1)
        'Modules/Finance/Support/LifecycleDueExceptionReasonResolver.php',
        'Modules/Finance/Support/LifecycleDueItemPredicate.php',
        // F — the tie-break-winning `primaryEnrollment` relation is empty (no materialized
        // enrollment) branch inside `lifecycleStatus()` — the documented fallback, not a bypass
        'Models/Student.php',
        // N — audit trail logs the column's own old/new value; a projected value would corrupt it
        'Models/StudentAuditableModel.php',
        // H — per-semester timeline reconstruction (deliberately historical)
        'Modules/Academic/Progression/Queries/Reporting/GetStudentStatusBySemesterQuery.php',
        // H — intake seed: bootstraps the enrollment system FROM the column
        'Modules/Academic/Progression/Actions/MaterializeProgramEnrollmentAction.php',
        'Modules/Academic/Progression/Support/EloquentProgramEnrollmentReader.php',
        'Modules/Academic/Progression/Actions/ProcessEgcCourseResultsAction.php',
        // H — one-shot historical migration command
        'Console/Commands/Academic/MigrateStudentProgressionEventsCommand.php',
        // H — descoped (H6): mass-writes with no prior-value capture
        'Console/Commands/UpdateDeferredStudentsCourseRegistrations.php',
        // H — whitelist-historical (user decision 2): EGC results feeder
        'Services/CourseCompletionService.php',
    ];
}

it('keeps students.status reads to the phase-0 classified whitelist', function (): void {
    $appRoot = base_path('app');
    $whitelist = legacyStatusGuardWhitelist();

    $hits = collect(File::allFiles($appRoot))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(function (SplFileInfo $file): bool {
            $contents = file_get_contents($file->getPathname()) ?: '';

            return legacyStatusGuardIsCandidate($file->getRelativePathname(), $contents);
        })
        ->filter(function (SplFileInfo $file): bool {
            $contents = file_get_contents($file->getPathname()) ?: '';

            return legacyStatusGuardHasRead(legacyStatusGuardStripComments($contents), $file->getRelativePathname());
        })
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();

    $sortedWhitelist = collect($whitelist)->sort()->values()->all();

    $unexpected = array_values(array_diff($hits, $sortedWhitelist));
    $stale = array_values(array_diff($sortedWhitelist, $hits));

    expect($unexpected)->toBe([], 'Unexpected students.status reads (not in the phase-0 whitelist): '.implode(', ', $unexpected))
        ->and($stale)->toBe([], 'Stale whitelist entries (no longer read students.status): '.implode(', ', $stale));
});

it('strips comments before matching, ignoring comment-only mentions', function (): void {
    $code = <<<'PHP'
        <?php
        use App\Models\Student;
        // students.status is legacy; prefer the live enrollment projection.
        /** @see students.status */
        final class CommentOnly
        {
            public function noop(): void {}
        }
        PHP;

    $stripped = legacyStatusGuardStripComments($code);

    expect(legacyStatusGuardHasRead($stripped, 'Modules/Fake/CommentOnly.php'))->toBeFalse();
});

it('proves the guard works both ways: red on a Student-model violation, green on a DTO-only read', function (): void {
    $violation = <<<'PHP'
        <?php
        use App\Models\Student;
        final class ViolatesGuard
        {
            public function check(Student $student): bool
            {
                return $student->status === 'active';
            }
        }
        PHP;

    $dtoOnly = <<<'PHP'
        <?php
        use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
        final class DtoOnlyRead
        {
            public function check(StudentReference $student): bool
            {
                return $student->status === 'active';
            }
        }
        PHP;

    $violationIsCandidate = legacyStatusGuardIsCandidate('Modules/Fake/ViolatesGuard.php', $violation);
    $violationHasRead = legacyStatusGuardHasRead(legacyStatusGuardStripComments($violation), 'Modules/Fake/ViolatesGuard.php');

    $dtoIsCandidate = legacyStatusGuardIsCandidate('Modules/Fake/DtoOnlyRead.php', $dtoOnly);

    expect($violationIsCandidate)->toBeTrue()
        ->and($violationHasRead)->toBeTrue()
        ->and($dtoIsCandidate)->toBeFalse();
});

it('resolves a no-enrollment student to the legacy column through all 4 chokepoints and reference() (M1 fixture coverage)', function (): void {
    $this->campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'FALLBACK001',
            'full_name' => 'Fallback Student',
            'email' => 'fallback001@example.com',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
        ])
        ->create();

    $fresh = $student->fresh();

    expect($fresh->isActive())->toBeTrue()
        ->and($fresh->isClassRosterActive())->toBeTrue()
        ->and(Student::active()->whereKey($student->id)->exists())->toBeTrue()
        ->and(Student::classRosterActive()->whereKey($student->id)->exists())->toBeTrue()
        ->and(app(StudentReferenceReader::class)->find((int) $student->id)?->status)->toBe('intake_course');
});
