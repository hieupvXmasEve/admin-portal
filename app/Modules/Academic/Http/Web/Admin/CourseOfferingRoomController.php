<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\ChangeCourseOfferingRoomAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingRoomChangeException;
use App\Modules\Academic\Http\Requests\CourseDelivery\ChangeCourseOfferingRoomRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

final class CourseOfferingRoomController extends Controller
{
    public function __invoke(ChangeCourseOfferingRoomRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        try {
            ChangeCourseOfferingRoomAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'campus_id' => (int) app('campus')->id,
                'room_id' => $request->integer('room_id'),
                'requested_by_user_id' => (int) ($request->user()?->id ?? 0),
            ]);

            return Redirect::back()->with('success', 'Room updated for all class sessions.');
        } catch (CourseOfferingRoomChangeException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        }
    }
}
