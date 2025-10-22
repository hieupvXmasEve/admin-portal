<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use App\Http\Resources\FormResponseResource;
use App\Http\Resources\FormResource;
use App\Http\Requests\ReviewFormResponseRequest;
use App\Models\FormResponse;
use App\Models\Form;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FormReviewController extends Controller
{
    public function __construct(
        protected ResponseService $responseService
    ) {}

    /**
     * Display list of responses for review.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $campus = Campus::findOrFail(app('campus')->id);

        $filters = $request->only(['status', 'form_id', 'pending_review', 'date_from', 'date_to']);

        $responses = $this->responseService->getResponsesForReview($user, $campus, $filters);

        // Get forms for filter dropdown
        $userRoleIds = $user->campusUserRoles()->pluck('role_id');

        $forms = Form::active()
            ->where(function ($query) use ($userRoleIds) {
                $query->whereDoesntHave('resultVisibility')
                    ->orWhereHas('resultVisibility', function ($visibilityQuery) use ($userRoleIds) {
                        $visibilityQuery->whereHas('role', function ($roleQuery) use ($userRoleIds) {
                            $roleQuery->whereIn('id', $userRoleIds);
                        });
                    });
            })
            ->get();
        Log::info('Forms', ['forms' => $forms]);
        return Inertia::render('Forms/Review/Index', [
            'responses' => FormResponseResource::collection($responses),
            'forms' => FormResource::collection($forms)->resolve($request),
            'filters' => $filters,
            'campus' => $campus,
        ]);
    }

    /**
     * Display a specific response for review.
     */
    public function show(FormResponse $response)
    {
        // Load relationships
        $response->load([
            'form.latestPublishedVersion.questions.options',
            'student',
            'campus',
            'answers.question',
            'answers.selectedOptions',
            'reviewer',
            'queryTicket.topic',
            'queryTicket.replies.author',
        ]);

        return Inertia::render('Forms/Review/Show', [
            'response' => new FormResponseResource($response),
        ]);
    }

    /**
     * Review a response (approve or reject).
     */
    public function review(ReviewFormResponseRequest $request, FormResponse $response)
    {
        $user = auth()->user();

        try {
            $response = $this->responseService->reviewResponse(
                $response,
                $user,
                $request->action,
                $request->notes
            );

            $message = $request->action === 'approve'
                ? 'Response has been approved successfully.'
                : 'Response has been rejected.';

            return redirect()->route('forms.review.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }
    }

    /**
     * Display analytics for a form.
     */
    public function analytics(Request $request, Form $form)
    {
        $user = auth()->user();
        $campus = Campus::findOrFail(session('current_campus_id'));

        // Check if user has permission to view analytics
        $hasPermission = $form->resultVisibility()
            ->whereHas('role', function ($q) use ($user) {
                $q->whereIn('id', $user->campusUserRoles()->pluck('role_id'));
            })
            ->where('visibility_level', '!=', 'own_submission')
            ->exists();

        if (!$hasPermission) {
            abort(403, 'You do not have permission to view analytics for this form.');
        }

        $filters = $request->only(['date_from', 'date_to']);
        $analytics = $this->responseService->getFormAnalytics($form, $campus, $filters);

        return Inertia::render('Forms/Analytics/Show', [
            'form' => new FormResource($form),
            'analytics' => $analytics,
            'filters' => $filters,
            'campus' => $campus,
        ]);
    }

    /**
     * Export responses to CSV.
     */
    public function export(Request $request, Form $form)
    {
        $user = auth()->user();
        $campus = Campus::findOrFail(session('current_campus_id'));

        // Check permission
        $hasPermission = $form->resultVisibility()
            ->whereHas('role', function ($q) use ($user) {
                $q->whereIn('id', $user->campusUserRoles()->pluck('role_id'));
            })
            ->where('visibility_level', 'full_detail')
            ->exists();

        if (!$hasPermission) {
            abort(403, 'You do not have permission to export responses for this form.');
        }

        $csv = $this->responseService->exportResponses($form, $campus);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $form->code . '_responses_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Bulk review responses.
     */
    public function bulkReview(Request $request)
    {
        $request->validate([
            'response_ids' => 'required|array',
            'response_ids.*' => 'exists:responses,id',
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $successCount = 0;
        $failedCount = 0;

        foreach ($request->response_ids as $responseId) {
            $response = FormResponse::find($responseId);

            if ($response && $response->canBeReviewedBy($user)) {
                try {
                    $this->responseService->reviewResponse(
                        $response,
                        $user,
                        $request->action,
                        $request->notes
                    );
                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        $message = "Successfully {$request->action}d {$successCount} response(s).";
        if ($failedCount > 0) {
            $message .= " Failed to process {$failedCount} response(s).";
        }

        return redirect()->route('forms.review.index')
            ->with($failedCount > 0 ? 'warning' : 'success', $message);
    }
}
