<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Actions\RunStaffCopilotMessageAction;
use App\Modules\AI\Http\Requests\SubmitStaffCopilotMessageRequest;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Queries\StaffCopilotPageQuery;
use App\Modules\AI\Support\StaffCopilotSseRuntime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffCopilotController extends Controller
{
    public function __construct(
        private readonly StaffCopilotPageQuery $pageQuery,
        private readonly StaffCopilotSseRuntime $runtime,
    ) {}

    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        return Inertia::render('AI/StaffCopilot/Index', $this->pageQuery->handle($user, $this->currentCampus()));
    }

    public function store(SubmitStaffCopilotMessageRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        RunStaffCopilotMessageAction::run(
            actor: $user,
            campus: $this->currentCampus(),
            question: $request->question(),
            conversationId: $request->conversationId(),
        );

        Inertia::flash('success', 'AI copilot run queued.');

        return redirect()->route('ai.copilot.index');
    }

    public function events(Request $request, AiChatRun $run): StreamedResponse
    {
        $this->authorizeRun($run, $request);

        $cursor = (int) $request->integer('cursor', 0);

        abort_unless($this->runtime->validateCursor($run, $cursor), 422, 'Invalid run event cursor.');

        return response()->eventStream(
            fn () => $this->runtime->stream($run, $cursor),
            headers: [
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    public function cancel(Request $request, AiChatRun $run): RedirectResponse
    {
        $this->authorizeRun($run, $request);
        $this->runtime->cancel($run);

        Inertia::flash('info', 'AI copilot run cancelled.');

        return redirect()->route('ai.copilot.index');
    }

    public function retry(Request $request, AiChatRun $run): RedirectResponse
    {
        $this->authorizeRun($run, $request);

        abort_unless($run->status === AiChatRun::STATUS_FAILED, 409, 'Only failed runs can be retried.');

        $this->runtime->retry($run);

        Inertia::flash('success', 'AI copilot run retried.');

        return redirect()->route('ai.copilot.index');
    }

    private function currentCampus(): ?Campus
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus instanceof Campus ? $campus : null;
    }

    private function authorizeRun(AiChatRun $run, Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user instanceof User && $run->user_id === $user->id, 403);

        $campus = $this->currentCampus();

        if ($run->campus_id === null) {
            abort_unless($campus === null, 403);

            return;
        }

        abort_unless($campus instanceof Campus && $run->campus_id === $campus->id, 403);
    }
}
