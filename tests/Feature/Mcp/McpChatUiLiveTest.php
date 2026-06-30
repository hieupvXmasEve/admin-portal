<?php

declare(strict_types=1);

use App\Modules\AI\Http\Web\Admin\StaffCopilotController;
use App\Modules\AI\Support\LiveStaffCopilotAgent;
use App\Modules\AI\Support\StaffCopilotAgentRunner;
use App\Modules\AI\Support\StaffCopilotSseRuntime;
use Illuminate\Support\Facades\Route;

/**
 * Confirms the frozen-but-live decision (PRD / ADR-0009): the in-house Staff Copilot
 * chat UI and its synthesis stack stay running in parallel with the MCP server so staff
 * without an external agent client are not stranded. Hardening must NOT neutralize the
 * chat route or delete the synthesis stack — only the public MCP surface is wrapped.
 *
 * This is a guard against an over-eager "neutralize the old chat UI" change, not a test
 * of the (frozen, unedited) chat behavior itself.
 */
it('keeps the Staff Copilot chat UI route registered and live', function () {
    expect(Route::has('ai.copilot.index'))->toBeTrue()
        ->and(Route::has('ai.copilot.messages.store'))->toBeTrue()
        ->and(Route::has('ai.copilot.runs.events'))->toBeTrue();

    $route = Route::getRoutes()->getByName('ai.copilot.index');

    expect($route->uri())->toBe('ai/copilot')
        ->and($route->getActionName())->toContain(StaffCopilotController::class)
        // Still gated by the existing AI-metrics permission — not neutralized/opened.
        ->and($route->gatherMiddleware())->toContain('can:view_ai_metrics');
});

it('keeps the chat synthesis stack present and resolvable', function () {
    foreach ([
        StaffCopilotSseRuntime::class,
        StaffCopilotAgentRunner::class,
        LiveStaffCopilotAgent::class,
        StaffCopilotController::class,
    ] as $class) {
        expect(class_exists($class))->toBeTrue("Frozen synthesis-stack class {$class} must not be deleted");
    }
});
