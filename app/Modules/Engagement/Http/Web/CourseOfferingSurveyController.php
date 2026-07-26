<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Actions\ProvisionCourseSurveyAction;
use App\Modules\Engagement\Http\Requests\Forms\ProvisionCourseSurveyRequest;
use App\Shared\Contracts\Academic\CourseOfferingSurveyContextReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class CourseOfferingSurveyController extends Controller
{
    public function __invoke(
        ProvisionCourseSurveyRequest $request,
        mixed $courseOffering,
        CourseOfferingSurveyContextReader $surveyContexts,
        ProvisionCourseSurveyAction $provisionSurvey,
    ): RedirectResponse {
        $courseOfferingId = is_object($courseOffering) ? (int) $courseOffering->id : (int) $courseOffering;
        $context = $surveyContexts->forOffering($courseOfferingId);

        if ($context->campusId !== (int) app('campus')->id) {
            abort(404);
        }

        try {
            $provisionSurvey->provisionForCourseOffering(
                $context,
                (int) $request->validated('form_id'),
            );

            Inertia::flash('message', 'Survey created and assigned to students successfully.');

            return Redirect::back();
        } catch (ValidationException $exception) {
            return Redirect::back()->withErrors($exception->errors());
        } catch (\Exception $exception) {
            Log::error('Failed to create survey: '.$exception->getMessage());

            Inertia::flash('error', 'Failed to create survey: '.$exception->getMessage());

            return Redirect::back();
        }
    }
}
