<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Actions\SetSelectedAcademicPeriodAction;
use App\Modules\Academic\Catalog\Http\Requests\SetSelectedAcademicPeriodRequest;
use Illuminate\Http\RedirectResponse;

class SelectedAcademicPeriodController extends Controller
{
    public function update(SetSelectedAcademicPeriodRequest $request): RedirectResponse
    {
        SetSelectedAcademicPeriodAction::run($request->academicPeriodId());

        return back();
    }
}
