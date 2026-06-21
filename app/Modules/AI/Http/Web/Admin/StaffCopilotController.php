<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Actions\RunStaffCopilotMessageAction;
use App\Modules\AI\Http\Requests\SubmitStaffCopilotMessageRequest;
use App\Modules\AI\Queries\StaffCopilotPageQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffCopilotController extends Controller
{
    public function __construct(private readonly StaffCopilotPageQuery $pageQuery) {}

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

        $answer = RunStaffCopilotMessageAction::run(
            actor: $user,
            campus: $this->currentCampus(),
            question: $request->question(),
            conversationId: $request->conversationId(),
        );

        if ($answer->isSuccessful()) {
            Inertia::flash('success', 'AI copilot answer generated.');
        } else {
            Inertia::flash('error', 'AI copilot could not answer this question yet.');
        }

        return redirect()->route('ai.copilot.index');
    }

    private function currentCampus(): ?Campus
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus instanceof Campus ? $campus : null;
    }
}
