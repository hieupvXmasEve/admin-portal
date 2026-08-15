<?php

declare(strict_types=1);

/**
 * Guard for plan 260811-0012 (deprecated model shim namespace sweep).
 *
 * `app/Models/*.php` shims are `class_alias()` one-liners kept for backward
 * compatibility while callers migrate to their owning module's namespace
 * (see 260809-1557). This test does two things:
 *
 * 1. Caps the shim count at the known allow-list — new shims must not
 *    appear, and each phase of the sweep shrinks this list as it deletes
 *    shims. An empty allow-list is the sweep's completion signal.
 * 2. Caps the *importer* list at a recorded baseline — new files must not
 *    start importing `App\Models\<shim>`. Existing importers are
 *    grandfathered until their module's phase sweeps them; each phase
 *    shrinks this list too. An empty importer list (alongside an empty
 *    shim list) is the sweep's completion signal.
 */

// All 30 models moved by 260809-1557. Fixed, never shrinks — the import guard
// must keep watching a model after its shim is deleted, or a missed caller
// becomes invisible exactly when the shim stops covering for it (phase 3, D3).
const ALL_MIGRATED_MODELS = [
    'ApplicationDocument', 'ApplicationDocumentType', 'Building', 'Club',
    'ClubMember', 'ClubMemberRoleHistory', 'Event', 'EventParticipant',
    'Form', 'FormResponse', 'FormResultVisibility', 'FormSection',
    'FormSurvey', 'FormTarget', 'FormVersion', 'GoldTransaction',
    'Merchandise', 'MerchandiseImage', 'MerchandiseVariant', 'QueryAssignment',
    'QueryReply', 'QueryTicket', 'QueryTopic', 'RedemptionOrder',
    'RedemptionOrderItem', 'Room', 'RoomBooking', 'RoomBookingAction',
    'StockMovement', 'UploadRecord',
];

// Shrink this list as each module phase deletes its shims (see plan.md). Only
// used as the still-shimmed allow-list; the import regex is built from
// ALL_MIGRATED_MODELS instead so it never blinds itself (phase 3, D3).
//
// 24 of the 30 migrated models have had their app/Models shim swept and
// deleted already: Merchandise, MerchandiseImage, MerchandiseVariant,
// RedemptionOrder, RedemptionOrderItem, StockMovement, Club, ClubMember,
// ClubMemberRoleHistory, Event, EventParticipant, Form,
// FormResultVisibility, FormSection, FormSurvey, FormVersion, FormTarget,
// QueryAssignment, QueryTopic, ApplicationDocumentType, Building, Room,
// RoomBooking, RoomBookingAction.
//
// Room, Building, FormTarget, and ApplicationDocumentType all joined that
// list the same way: the cross-module read blocking each (Academic reading
// rooms, Academic counting buildings per campus, Academic reading a course's
// survey target, Admissions reading the document-type catalog) moved behind
// a contract implemented in the data's owning module —
// App\Shared\Contracts\Facilities\SpaceReferenceReader,
// App\Shared\Contracts\Academic\CampusBuildingCountReader (implemented in
// Facilities despite the Academic-named namespace),
// App\Shared\Contracts\Engagement\CourseSurveyTargetReader, and
// App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader. That is the
// shape the remaining six need: a shim survives only because a live
// cross-module read still resolves through it, and rewriting that caller to
// the canonical namespace would trip the zero-tolerance
// cross_context_concrete_imports boundary rule. Routing the read through a
// shared contract removes the blocker; sweeping the import alone just moves
// the violation into view.
//
// ApplicationDocument is the odd one out among the six: unlike the other
// five, it is not blocked by a read alone.
// App\Modules\Admissions\Actions\UpsertCrmApplicationAction writes it
// (updateOrCreate with two different field shapes for the push vs NE CRM
// sync paths, plus a delete-not-in-set reconciliation) as part of what its
// own docblock calls "the live write path" for fraud-sensitive CRM ingest
// (findings 7 and 12). Collapsing that into a generic writer contract risks
// silently changing which fields get overwritten on update — deferred
// pending its own design pass rather than rushed alongside the read-only
// sweeps.
//
// Written as a literal (not array_diff) because top-level `const` requires a
// compile-time constant expression.
const SHIMMED_MODELS = [
    'ApplicationDocument',
    'FormResponse', 'GoldTransaction',
    'QueryReply', 'QueryTicket', 'UploadRecord',
];

// Baseline of files that already reference `App\Models\<shim>`, measured
// 2026-08-11 (plan Evidence Base). Grandfathered until each module's phase
// sweeps its callers — shrink this list then, never grow it. The two morph
// backfill migrations are permanent exceptions, not temporary grandfathering:
// they intentionally store the old FQCN as data (a WHERE clause value), not
// as an import, and historical migrations are never rewritten or deleted.
const SHIMMED_MODEL_IMPORT_BASELINE = [
    'app/Models/Answer.php',
    'app/Http/Controllers/Api/GoldTransactionController.php',
    'app/Http/Controllers/Api/V1/Admissions/IngestionController.php',
    'app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php',
    'app/Modules/Engagement/Actions/EventParticipationOperations.php',
    'app/Modules/Engagement/Models/FormResponse.php',
    'app/Modules/Engagement/Models/QueryReply.php',
    'app/Modules/Upload/Models/UploadRecord.php',
    'app/Services/Admissions/ApplicationBackfillService.php',
    'app/Services/Admissions/ApplicationIngestionService.php',
    'app/Services/ApplicationDocumentService.php',
    'app/Services/GoldService.php',
    'database/migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php',
    'database/migrations/2026_08_11_085516_backfill_remaining_shimmed_morph_subject_types.php',
    'tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php',
    'tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php',
    'tests/Feature/Academic/StudentLifecycleTimelineQueryTest.php',
    'tests/Feature/Admissions/ApplicationBackfillTest.php',
    'tests/Feature/Admissions/IngestionApplicationsTest.php',
    'tests/Feature/Api/V1/Student/EngagementFormsApiTest.php',
    'tests/Feature/Api/V1/Student/QueryTicketApiTest.php',
    'tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php',
    'tests/Feature/Form/AdminQueryInboxTest.php',
    'tests/Feature/Form/QueryTicketWorkflowTest.php',
    'tests/Feature/Form/SurveyAggregateConfigTest.php',
    'tests/Feature/Form/SurveyResultDownloadTest.php',
    'tests/Feature/Gold/GoldServiceTest.php',
    'tests/Feature/Gold/GoldTransactionOwnershipTest.php',
    'tests/Feature/Gold/ReclaimGoldRewardTest.php',
    'tests/Feature/Lecture/LecturerGpaReportTest.php',
    'tests/Feature/Merchandise/Redemption/RedemptionAccessControlTest.php',
    'tests/Feature/Merchandise/Redemption/RedemptionCheckoutTest.php',
    'tests/Feature/Merchandise/Redemption/RedemptionRefundAndStateMachineTest.php',
    'tests/Feature/Merchandise/Reports/MerchandiseReportDataTest.php',
    'tests/Feature/Platform/SystemConfigurationMigrationTest.php',
    'tests/Feature/StudentApplication/DocumentsTest.php',
    'tests/Feature/StudentApplication/ExportTest.php',
    'tests/Feature/StudentApplication/IndexCampusScopeTest.php',
    'tests/Feature/Upload/UploadPlatformTest.php',
];

it('has no class_alias shim under app/Models beyond the known allow-list', function (): void {
    $workspace = dirname(__DIR__, 3);
    $modelsDir = $workspace.'/app/Models';

    // Recursive: a shim reintroduced in a subdirectory (e.g. app/Models/Engagement/Club.php)
    // is a real shim and must not evade this scan (phase 3, D2).
    $shimmedFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelsDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (str_contains($contents, 'class_alias(')) {
            $shimmedFiles[] = basename($file->getPathname(), '.php');
        }
    }

    sort($shimmedFiles);
    $expected = SHIMMED_MODELS;
    sort($expected);

    expect($shimmedFiles)->toBe($expected, 'New class_alias shims must not be added to app/Models. Update the SHIMMED_MODELS allow-list in this test only when deleting a shim, never when adding one.');
});

it('has no App\Models\<shim> reintroduced by any means once its shim is deleted', function (): void {
    // A subclass (`class Club extends \App\Modules\Engagement\Models\Club {}`)
    // creates a genuinely distinct class with no `class_alias(` text anywhere,
    // so it passes the scan above but still resurrects the persisted-FQCN
    // problem the sweep exists to close (phase 3, D2b). Behavioral, not textual.
    $sweptModels = array_values(array_diff(ALL_MIGRATED_MODELS, SHIMMED_MODELS));

    $stillResolvable = array_values(array_filter(
        $sweptModels,
        fn (string $model): bool => class_exists('App\\Models\\'.$model)
    ));

    expect($stillResolvable)->toBe([], "App\\Models\\<Name> must not resolve for a swept model, by class_alias, subclass, or otherwise:\n".implode("\n", $stillResolvable));
});

it('has no unqualified reference to a swept model from inside the App\Models namespace', function (): void {
    // Inside the shim namespace a bare `Room::class` resolves to the shim
    // itself — no import, no fully qualified name, nothing for the textual
    // scan below to match (that scan reads whole files, so this note avoids
    // spelling the pattern out). ClassSession and ExamRoomSlot both bound
    // `belongsTo(Room::class)` that way; deleting the Room shim broke them at
    // runtime while every textual guard stayed green. The same scan then found
    // five older relations already broken by phase 4's deletions.
    //
    // PHP does not fall back to the global namespace for class names, so only
    // files declaring the shim namespace can bind a swept model this way.
    $workspace = dirname(__DIR__, 3);
    $sweptModels = array_values(array_diff(ALL_MIGRATED_MODELS, SHIMMED_MODELS));

    $offenders = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace.'/app/Models', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/^namespace\s+App\\\\Models;/m', $contents) !== 1) {
            continue;
        }

        $relativePath = ltrim(substr($file->getPathname(), strlen($workspace)), '/');

        foreach ($sweptModels as $model) {
            // A bare `Model::` or a bare type hint. An aliased import of the
            // canonical class makes the same token resolve correctly, so a
            // file that imports it is not an offender.
            if (preg_match('/(?<![\\\\\w])'.preg_quote($model, '/').'::/', $contents) !== 1) {
                continue;
            }

            if (preg_match('/^use\s+App\\\\Modules\\\\[A-Za-z0-9_\\\\]+\\\\'.preg_quote($model, '/').';/m', $contents) === 1) {
                continue;
            }

            $offenders[] = $relativePath.' → '.$model;
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], "A file in namespace App\\Models references a swept model unqualified, which resolves to the deleted App\\Models\\<Name> at runtime. Import the canonical App\\Modules\\<Owner>\\Models\\<Name> instead:\n".implode("\n", $offenders));
});

it('has no new caller importing App\Models\<shim> beyond the recorded baseline', function (): void {
    $workspace = dirname(__DIR__, 3);
    $scanDirs = ['app', 'tests', 'database', 'routes', 'config'];

    // Matches both the raw single-backslash FQCN form (use-statements,
    // unescaped references) and the double-backslash form written inside
    // double-quoted PHP string literals (e.g. morph-type config values) —
    // \\{1,2} matches one or two literal backslash characters in the
    // scanned source text.
    $modelAlternation = implode('|', array_map(fn (string $m) => preg_quote($m, '/'), ALL_MIGRATED_MODELS));
    $pattern = '/\bApp\\\\{1,2}Models\\\\{1,2}('.$modelAlternation.')\b/';

    $found = [];

    foreach ($scanDirs as $dir) {
        $fullDir = $workspace.'/'.$dir;
        if (! is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = ltrim(substr($file->getPathname(), strlen($workspace)), '/');

            $contents = file_get_contents($file->getPathname()) ?: '';

            // Only the shim files themselves legitimately reference App Models <Model>
            // via class_alias(). Skipping the whole app/Models/ directory hid a real
            // caller (app/Models/Answer.php importing the UploadRecord shim) —
            // skip by content, not by directory (phase 3, D1).
            if (str_starts_with($relativePath, 'app/Models/') && str_contains($contents, 'class_alias(')) {
                continue;
            }

            if (preg_match($pattern, $contents) === 1) {
                $found[] = $relativePath;
            }
        }
    }

    sort($found);
    $baseline = SHIMMED_MODEL_IMPORT_BASELINE;
    sort($baseline);

    $newOffenders = array_values(array_diff($found, $baseline));
    expect($newOffenders)->toBe([], "New callers must use the canonical App\\Modules\\<Owner>\\Models namespace instead of the deprecated App\\Models shim:\n".implode("\n", $newOffenders));

    // Exact match (not just "no growth") so a completed module sweep must
    // shrink SHIMMED_MODEL_IMPORT_BASELINE — an empty list becomes the
    // sweep's completion signal, as the file docblock promises.
    $staleEntries = array_values(array_diff($baseline, $found));
    expect($staleEntries)->toBe([], "SHIMMED_MODEL_IMPORT_BASELINE has stale entries whose imports were already swept — shrink the list to match:\n".implode("\n", $staleEntries));
});
