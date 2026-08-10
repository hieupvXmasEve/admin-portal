<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Http\Requests\Forms\UpdateAggregateConfigRequest;
use App\Modules\Engagement\Models\Form;

class SurveyAggregateConfigController extends Controller
{
    public function update(UpdateAggregateConfigRequest $request, Form $form)
    {
        $overall = $request->validated()['overall'];

        $form->forceFill(['aggregate_config' => [
            'overall' => [
                'question_codes' => array_values(array_unique($overall['question_codes'])),
                'thresholds' => [
                    'positive_min' => (int) $overall['thresholds']['positive_min'],
                    'negative_max' => (int) $overall['thresholds']['negative_max'],
                ],
            ],
        ]])->save();

        return back()->with('success', 'Overall rating configuration saved.');
    }

    public function destroy(Form $form)
    {
        $this->authorize('configure_survey_aggregate');

        $form->forceFill(['aggregate_config' => null])->save();

        return back()->with('success', 'Overall rating configuration reset to default.');
    }
}
