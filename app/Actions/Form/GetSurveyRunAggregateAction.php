<?php

namespace App\Actions\Form;

use App\Models\Answer;
use App\Models\FormResponse;
use App\Models\FormTarget;
use App\Models\CourseOffering;
use Illuminate\Support\Facades\DB;

class GetSurveyRunAggregateAction
{
    /**
     * Get aggregated data for a specific survey run.
     */
    public function execute(FormTarget $target): array
    {
        // 1. Load context info
        $target->load(['form', 'semester', 'formVersion']);
        
        $courseInfo = null;
        if ($target->scope_type === 'course') {
            $offering = CourseOffering::with(['unit', 'lecture'])->find($target->scope_id);
            if ($offering) {
                // Determine instructor name from physical columns as display_name might be an accessor
                $instructor = $offering->lecture 
                    ? trim(($offering->lecture->title ? $offering->lecture->title . ' ' : '') . $offering->lecture->first_name . ' ' . $offering->lecture->last_name)
                    : null;

                $courseInfo = [
                    'code' => $offering->unit?->code,
                    'name' => $offering->unit?->name,
                    'section' => $offering->section_code,
                    'instructor' => $instructor,
                ];
            }
        }

        // 2. Response counts
        $assignments = DB::table('student_form_assignments')
            ->where('form_target_id', $target->id)
            ->get();
        
        $responsesTotal = $assignments->count();
        $responseIds = $assignments->whereNotNull('response_id')->pluck('response_id');
        $responsesDone = $responseIds->count();
        $responsePercent = $responsesTotal > 0 ? round(($responsesDone / $responsesTotal) * 100) : 0;

        // 3. Load Version with Sections and Questions
        $version = $target->formVersion()->with('sections.questions')->first();
        if (!$version) return [];

        // 4. Calculate Stats
        // We focus on rating questions for the Aggregate page
        $allRatings = Answer::whereIn('response_id', $responseIds)
            ->whereNotNull('answer_number')
            ->get();

        // Overall KPIs
        $overallAvg = $allRatings->avg('answer_number');
        $overallTotal = $allRatings->count();
        $overallPositive = $allRatings->where('answer_number', '>=', 4)->count();
        $overallNeutral = $allRatings->where('answer_number', 3)->count();
        $overallNegative = $allRatings->where('answer_number', '<=', 2)->count();

        $sections = [];
        $questions = [];

        foreach ($version->sections as $section) {
            $sectionQuestionIds = $section->questions->where('type', 'rating')->pluck('id');
            $sectionRatings = $allRatings->whereIn('question_id', $sectionQuestionIds);
            
            if ($sectionRatings->isNotEmpty()) {
                $sections[] = [
                    'title' => $section->title,
                    'questions_count' => $sectionQuestionIds->count(),
                    'average' => (float) round($sectionRatings->avg('answer_number'), 1),
                    'positive_percent' => $sectionRatings->count() > 0 
                        ? round(($sectionRatings->where('answer_number', '>=', 4)->count() / $sectionRatings->count()) * 100) 
                        : 0,
                    'negative_percent' => $sectionRatings->count() > 0 
                        ? (int) round(($sectionRatings->where('answer_number', '<=', 2)->count() / $sectionRatings->count()) * 100) 
                        : 0,
                    'distribution' => $this->getDistribution($sectionRatings),
                ];

                foreach ($section->questions->where('type', 'rating') as $question) {
                    $qRatings = $sectionRatings->where('question_id', $question->id);
                    $questions[] = [
                        'text' => $question->title,
                        'average' => (float) round($qRatings->avg('answer_number'), 1),
                        'distribution' => $this->getDistribution($qRatings),
                    ];
                }
            }
        }

        return [
            'header' => [
                'course_info' => $courseInfo,
                'semester' => $target->semester?->name,
                'form_title' => $target->form?->title,
                'form_version' => $target->formVersion?->version_name ?? '#'.$target->formVersion?->id,
                'responses_done' => $responsesDone,
                'responses_total' => $responsesTotal,
                'responses_percent' => (int) $responsePercent,
            ],
            'overall' => [
                'average' => (float) round($overallAvg, 1),
                'positive_percent' => $overallTotal > 0 ? (int) round(($overallPositive / $overallTotal) * 100) : 0,
                'neutral_percent' => $overallTotal > 0 ? (int) round(($overallNeutral / $overallTotal) * 100) : 0,
                'negative_percent' => $overallTotal > 0 ? (int) round(($overallNegative / $overallTotal) * 100) : 0,
            ],
            'sections' => $sections,
            'questions' => $questions,
        ];
    }

    /**
     * Helper to get 1-5 distribution counts.
     */
    private function getDistribution($ratings): array
    {
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($ratings as $r) {
            $val = (int) $r->answer_number;
            if (isset($counts[$val])) {
                $counts[$val]++;
            }
        }
        return array_values($counts); // [count1, count2, count3, count4, count5]
    }
}
