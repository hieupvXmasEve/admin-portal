<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\ApproveRestorationProposalAction;
use App\Modules\Finance\Actions\CreateRestorationProposalAction;
use App\Modules\Finance\Actions\RejectRestorationProposalAction;
use App\Modules\Finance\Http\Requests\ScholarshipRestoration\ApproveScholarshipRestorationRequest;
use App\Modules\Finance\Http\Requests\ScholarshipRestoration\ProposeScholarshipRestorationRequest;
use App\Modules\Finance\Http\Requests\ScholarshipRestoration\RejectScholarshipRestorationRequest;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Web decision loop for restoration proposals (Phase 2). Thin — all business
 * rules (permission re-check at campus, duplicate guard, floor validation,
 * dossier close on approve) live in the three existing Actions.
 */
class ScholarshipRestorationController extends Controller
{
    public function propose(
        ProposeScholarshipRestorationRequest $request,
        ScholarshipSemesterAdjustment $adjustment,
        CreateRestorationProposalAction $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->run(
                (int) $adjustment->id,
                $validated['reason'],
                (int) $request->user()->id,
                isset($validated['restored_amount']) ? (float) $validated['restored_amount'] : null,
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        Inertia::flash('success', 'Đã gửi đề xuất khôi phục học bổng.');

        return back();
    }

    public function approve(
        ApproveScholarshipRestorationRequest $request,
        ScholarshipRestorationProposal $proposal,
        ApproveRestorationProposalAction $action,
    ): RedirectResponse {
        try {
            $action->run((int) $proposal->id, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        Inertia::flash('success', 'Đã duyệt khôi phục học bổng.');

        return back();
    }

    public function reject(
        RejectScholarshipRestorationRequest $request,
        ScholarshipRestorationProposal $proposal,
        RejectRestorationProposalAction $action,
    ): RedirectResponse {
        $reason = $request->validated()['reason'];

        try {
            $action->run((int) $proposal->id, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        Log::info('scholarship_restoration_rejected', [
            'proposal_id' => $proposal->id,
            'rejected_by_user_id' => $request->user()->id,
            'reason' => $reason,
        ]);

        Inertia::flash('success', 'Đã từ chối đề xuất khôi phục.');

        return back();
    }
}
