<?php

declare(strict_types=1);

/**
 * Structural invariants for resources/js/constants/menu-sidebar.ts.
 *
 * The sidebar is a plain TS data structure (no build step runs before these
 * tests), so invariants are enforced by parsing the raw file text rather than
 * importing it. `title`/`label` are Vue v-for keys (NavMain.vue, NavMenuItem.vue),
 * so uniqueness here is not cosmetic.
 */
function sidebarMenuSource(): string
{
    return file_get_contents(base_path('resources/js/constants/menu-sidebar.ts'));
}

/**
 * Return the byte offset of the `]` that closes the `[` opened at $openBracketPos.
 */
function matchingCloseBracket(string $source, int $openBracketPos): int
{
    $depth = 0;
    $length = strlen($source);

    for ($i = $openBracketPos; $i < $length; $i++) {
        if ($source[$i] === '[') {
            $depth++;
        } elseif ($source[$i] === ']') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    throw new RuntimeException('Unbalanced brackets starting at offset '.$openBracketPos);
}

/**
 * @return array<int, array{start: int, end: int}> byte spans of every `children: [ ... ]` array.
 */
function childrenSpans(string $source): array
{
    $spans = [];
    $offset = 0;

    while (($pos = strpos($source, 'children: [', $offset)) !== false) {
        $openBracket = $pos + strlen('children: ');
        $close = matchingCloseBracket($source, $openBracket);
        $spans[] = ['start' => $openBracket, 'end' => $close];
        $offset = $openBracket + 1;
    }

    return $spans;
}

it('has no nesting deeper than group > item > child', function () {
    $source = sidebarMenuSource();
    $spans = childrenSpans($source);

    foreach ($spans as $span) {
        foreach ($spans as $other) {
            if ($other === $span) {
                continue;
            }

            $nested = $other['start'] > $span['start'] && $other['start'] < $span['end'];
            expect($nested)->toBeFalse(
                "children: [ at offset {$other['start']} is nested inside another children: [ spanning {$span['start']}-{$span['end']}; max depth is group > item > child"
            );
        }
    }
});

it('has no wrapper item carrying requiredPermissions', function () {
    $source = sidebarMenuSource();

    // A wrapper is `href: '#'` followed by an icon and then `children: [`,
    // with no `requiredPermissions` in between (usePermissions.ts:36-44 never
    // evaluates a wrapper's own requiredPermissions).
    preg_match_all("/href: '#',/", $source, $wrapperMatches, PREG_OFFSET_CAPTURE);
    $wrapperCount = count($wrapperMatches[0]);

    preg_match_all("/href: '#',\s*icon: \w+,\s*children: \[/", $source, $cleanWrapperMatches);
    $cleanWrapperCount = count($cleanWrapperMatches[0]);

    expect($cleanWrapperCount)->toBe($wrapperCount, 'every href: \'#\' wrapper must go straight from icon to children with no requiredPermissions in between');
});

it('has no duplicate sibling title and no duplicate group label', function () {
    $source = sidebarMenuSource();

    preg_match_all("/title: '([^']+)'/", $source, $titleMatches);
    $titles = $titleMatches[1];
    $duplicateTitles = array_diff_assoc($titles, array_unique($titles));
    expect($duplicateTitles)->toBe([], 'duplicate title values break Vue v-for keys: '.implode(', ', $duplicateTitles));

    preg_match_all("/label: '([^']+)'/", $source, $labelMatches);
    $labels = $labelMatches[1];
    $duplicateLabels = array_diff_assoc($labels, array_unique($labels));
    expect($duplicateLabels)->toBe([], 'duplicate group label values break Vue v-for keys: '.implode(', ', $duplicateLabels));
});

it('has a stable href multiset with no duplicates', function () {
    $source = sidebarMenuSource();

    preg_match_all('/href: ([^,\n]+),/', $source, $hrefMatches);
    $hrefOccurrences = count($hrefMatches[0]);
    $hrefCountInSource = substr_count($source, 'href:');
    expect($hrefOccurrences)->toBe($hrefCountInSource, 'the href extraction regex must not silently drop any href: occurrence');

    $hrefs = $hrefMatches[1];
    $realHrefs = array_values(array_filter($hrefs, fn (string $href) => $href !== "'#'"));

    $duplicateHrefs = array_diff_assoc($realHrefs, array_unique($realHrefs));
    expect($duplicateHrefs)->toBe([], 'duplicate href values found: '.implode(', ', $duplicateHrefs));

    // Fixture pinned at Phase 4 of plans/260816-2125-sidebar-menu-ia-restructure/.
    // Update deliberately when a route is intentionally added, moved, or removed —
    // never to silence this test.
    $expectedHrefs = [
        "'/admin/canvas/courses'",
        "'/admin/canvas/integrations'",
        "'/admin/modules'",
        "'/admin/notification-templates'",
        "'/course-statistics'",
        "'/dashboard'",
        "'/exam-resit'",
        "'/exam-schedule'",
        "'/failed-students'",
        "'/forms/admin'",
        "'/forms/admin/inbox'",
        "'/forms/admin/results'",
        "'/forms/admin/results/stats'",
        "'/forms/admin/runs'",
        "'/forms/admin/runs/create'",
        "'/retake-course'",
        "'/scholarship-adjustments'",
        "'/scholarships'",
        "'/student-applications'",
        "'/student-applications/crm-mappings'",
        "'/student-scholarships'",
        "'/tuition-plans'",
        "'/vouchers'",
        'academicSummaryRoutes.academicReport()',
        'academicSummaryRoutes.courseRanking()',
        'academicSummaryRoutes.gpaFinalization()',
        'academicSummaryRoutes.gpaHistory()',
        'academicSummaryRoutes.performanceDashboard()',
        'academicSummaryRoutes.studentCompletedUnits()',
        'academicSummaryRoutes.warningCenter()',
        'attendanceRoutes.attendance.index()',
        'courseRoutes.classSchedule()',
        'courseRoutes.offerings.index()',
        'courseRoutes.registrations.index()',
        'curriculumRoutes.curriculumVersions.index()',
        'curriculumRoutes.programs.index()',
        'curriculumRoutes.syllabusTemplates.index()',
        'curriculumRoutes.units.index()',
        'financeRoutes.audit()',
        'financeRoutes.batchStudio.dng()',
        'financeRoutes.batchStudio.hub()',
        'financeRoutes.cockpit.index()',
        'financeRoutes.collect.dngPaymentRequests()',
        'financeRoutes.collect.dngReceiptExceptions()',
        'financeRoutes.collect.dngWebhookEvents()',
        'financeRoutes.collect.dueReminders()',
        'financeRoutes.collect.payments()',
        'financeRoutes.collect.settlement()',
        'financeRoutes.exceptions.lifecycle()',
        'financeRoutes.exceptions.lifecycleHistory()',
        'financeRoutes.exceptions.queue()',
        'financeRoutes.feeGeneration.egcBlockResults()',
        'financeRoutes.feeGeneration.egcCarryForward()',
        'financeRoutes.lookup.chargeLedger()',
        'financeRoutes.lookup.invoices()',
        'financeRoutes.pricingOperations.index()',
        'financeRoutes.reporting.index()',
        'financeRoutes.revenue.index()',
        'financeRoutes.settings.show()',
        'lecturerRoutes.gpa()',
        'lecturerRoutes.index()',
        'lecturerRoutes.teachingHours()',
        'studentRoutes.academicProgressionAudit()',
        'studentRoutes.academicProgressionDeferReturns()',
        'studentRoutes.academicProgressionMissingDecisions()',
        'studentRoutes.academicProgressionMissingDocuments()',
        'studentRoutes.enrollments()',
        'studentRoutes.list()',
        'studentRoutes.scholarshipRestorationWatchlist()',
        'studentRoutes.studentDecisionsIndex()',
        'studentRoutes.studentLifecycleYearlyAnalysis()',
        'studentRoutes.studentStatusAction()',
        'systemRoutes.activityLogs.index()',
        'systemRoutes.aiCopilot.index()',
        'systemRoutes.aiProviderSettings.index()',
        'systemRoutes.campuses.index()',
        'systemRoutes.clubs.index()',
        'systemRoutes.config.index()',
        'systemRoutes.departments.index()',
        'systemRoutes.emailConfiguration.emailHistory()',
        'systemRoutes.emailConfiguration.index()',
        'systemRoutes.events.index()',
        'systemRoutes.events.reports()',
        'systemRoutes.merchandise.index()',
        'systemRoutes.merchandise.reports()',
        'systemRoutes.notifications.ops.deliveries()',
        'systemRoutes.notifications.ops.messages()',
        'systemRoutes.notifications.ops.outbox()',
        'systemRoutes.notifications.send()',
        'systemRoutes.redemptionOrders.index()',
        'systemRoutes.roles.index()',
        'systemRoutes.roomBookings.availability()',
        'systemRoutes.roomBookings.calendar()',
        'systemRoutes.roomBookings.index()',
        'systemRoutes.roomBookings.myBookings()',
        'systemRoutes.roomBookings.pending()',
        'systemRoutes.rooms.index()',
        'systemRoutes.semesters.index()',
        'systemRoutes.users.index()',
    ];

    $sortedActual = $realHrefs;
    sort($sortedActual);
    $sortedExpected = $expectedHrefs;
    sort($sortedExpected);

    expect($sortedActual)->toBe($sortedExpected);
});

it('has every requiredPermissions string resolvable in config/permission.php', function () {
    $source = sidebarMenuSource();
    $permissionConfig = file_get_contents(base_path('config/permission.php'));

    preg_match_all("/requiredPermissions: \[([^\]]+)\]/", $source, $blockMatches);

    $permissions = [];
    foreach ($blockMatches[1] as $block) {
        preg_match_all("/'([^']+)'/", $block, $permMatches);
        $permissions = array_merge($permissions, $permMatches[1]);
    }
    $permissions = array_unique($permissions);

    expect($permissions)->not->toBeEmpty();

    foreach ($permissions as $permission) {
        $exists = str_contains($permissionConfig, "'{$permission}' =>");
        expect($exists)->toBeTrue("menu permission '{$permission}' has no entry in config/permission.php");
    }
});
