<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Finance\Actions\Operations\AcknowledgeLifecycleDueExceptionAction;
use App\Modules\Finance\Actions\Operations\ResolveLifecycleDueExceptionAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Http\Requests\Operations\AcknowledgeLifecycleDueExceptionRequest;
use App\Modules\Finance\Http\Requests\Operations\ListLifecycleDueExceptionsRequest;
use App\Modules\Finance\Http\Requests\Operations\ResolveLifecycleDueExceptionRequest;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListLifecycleDueExceptionsQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LifecycleDueExceptionController extends Controller
{
    public function index(
        ListLifecycleDueExceptionsRequest $request,
        ListLifecycleDueExceptionsQuery $listQuery,
        GetLifecycleDueExceptionSummaryQuery $summaryQuery,
    ): Response {
        $validated = array_replace([
            'semester_id' => null,
            'lifecycle_status' => null,
            'review_status' => 'open',
            'due_status' => 'all',
            'fee_type' => null,
            'search' => '',
            'per_page' => 20,
            'sort' => 'due_date',
            'direction' => 'asc',
        ], $request->validated());

        $semesterId = FinanceSemesterContextResolver::selectedId();
        $validated['semester_id'] = $semesterId;
        $currentSemester = $semesterId ? Semester::find($semesterId) : null;

        $summaryFilters = collect($validated)
            ->only(['fee_type', 'due_status', 'search'])
            ->all();

        return Inertia::render('Finance/Operations/LifecycleExceptions', [
            'exceptions' => $listQuery->handle($validated),
            'summary' => $summaryQuery->handle($semesterId, $summaryFilters),
            'semesters' => Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']),
            'current_semester' => $currentSemester,
            'filters' => $validated,
            'permissions' => [
                'can_cancel_dng' => $request->user()?->can('create_finance_payments') ?? false,
                'can_void_charges' => $request->user()?->can('void_finance_charges') ?? false,
            ],
        ]);
    }

    public function acknowledge(
        AcknowledgeLifecycleDueExceptionRequest $request,
        DngPaymentRequest $dngPaymentRequest,
        AcknowledgeLifecycleDueExceptionAction $action,
    ): RedirectResponse {
        $this->assertCampusAccess($dngPaymentRequest);
        $this->assertLifecycleException($dngPaymentRequest);

        try {
            $action->run($dngPaymentRequest, (string) $request->validated('reason'), (int) $request->user()->id);
        } catch (\Throwable $e) {
            Inertia::flash('error', $e->getMessage());

            return back();
        }

        Inertia::flash('success', 'Đã ghi nhận ngoại lệ lifecycle.');

        return back();
    }

    public function resolve(
        ResolveLifecycleDueExceptionRequest $request,
        DngPaymentRequest $dngPaymentRequest,
        ResolveLifecycleDueExceptionAction $action,
    ): RedirectResponse {
        $this->assertCampusAccess($dngPaymentRequest);
        $this->assertLifecycleException($dngPaymentRequest);

        $resolutionAction = LifecycleDueExceptionResolutionAction::from((string) $request->validated('resolution_action'));
        $user = $request->user();

        try {
            $action->run(
                $dngPaymentRequest,
                $resolutionAction,
                (string) $request->validated('reason'),
                (int) $user->id,
                $user->can('create_finance_payments'),
                $user->can('void_finance_charges'),
            );
        } catch (AuthorizationException $e) {
            Inertia::flash('error', $e->getMessage());

            return back();
        } catch (\Throwable $e) {
            Inertia::flash('error', $e->getMessage());

            return back();
        }

        Inertia::flash('success', 'Đã xử lý ngoại lệ lifecycle.');

        return back();
    }

    private function assertCampusAccess(DngPaymentRequest $paymentRequest): void
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus instanceof Campus && $campus->id !== null && $paymentRequest->student?->campus_id !== (int) $campus->id) {
            throw new AuthorizationException;
        }
    }

    private function assertLifecycleException(DngPaymentRequest $paymentRequest): void
    {
        $paymentRequest->loadMissing('student:id,campus_id,status');

        if (! LifecycleDueItemPredicate::isLifecycleException($paymentRequest->student)) {
            throw new AuthorizationException('DNG request is not a lifecycle due exception.');
        }
    }
}
