<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Delivery\Http\Requests\CourseDelivery\ListCourseOfferingsRequest;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingCockpitQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingOperationalStateQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingScoresQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingSurveyQuery;
use App\Modules\Academic\Delivery\Queries\ListCourseOfferingCockpitQuery;
use App\Modules\Academic\Delivery\Queries\ListCourseOfferingSurveyFormsQuery;
use App\Support\SemesterContextResolver;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class CourseOfferingCockpitController extends Controller
{
    public function __construct(
        private readonly ListCourseOfferingCockpitQuery $listQuery,
        private readonly GetCourseOfferingCockpitQuery $showQuery,
        private readonly GetCourseOfferingOperationalStateQuery $operationalStateQuery,
        private readonly ListCourseOfferingSurveyFormsQuery $activeSurveyForms,
    ) {}

    public function index(ListCourseOfferingsRequest $request): Response
    {
        $campusId = (int) app('campus')->id;
        $data = $this->listQuery->handle(
            $request->validated(),
            $campusId,
            SemesterContextResolver::selectedId(),
        );

        $data['surveyForms'] = $this->activeSurveyForms->handle();

        return Inertia::render('CourseOfferings/Index', $data);
    }

    public function show(object $courseOffering): Response
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        $data = $this->showQuery->handle($courseOffering, (int) app('campus')->id);

        return Inertia::render('CourseOfferings/Show', [
            'courseOffering' => $data['courseOffering'],
            'operational_state' => $this->operationalStateQuery->handle($data['courseOffering'], Auth::user()),
            'availableRooms' => Inertia::once(fn (): array => $data['availableRooms']),
            'surveyForms' => Inertia::once(fn () => $this->activeSurveyForms->handle()),
            'siblingOfferings' => $data['siblingOfferings'],
            'scoresData' => Inertia::defer(
                fn (): array => GetCourseOfferingScoresQuery::handle($data['courseOffering']),
                'scores',
            ),
            'surveyData' => Inertia::defer(
                fn (): ?array => GetCourseOfferingSurveyQuery::handle($data['courseOffering']),
                'survey',
            ),
        ]);
    }
}
