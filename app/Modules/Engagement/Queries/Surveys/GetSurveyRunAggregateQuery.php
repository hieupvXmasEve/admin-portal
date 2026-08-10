<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries\Surveys;

use App\Models\Answer;
use App\Models\CourseOffering;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Support\SurveyAggregateConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GetSurveyRunAggregateQuery
{
    /**
     * Get header/KPI data only — cheap, always eager.
     * Loads course info + response counts + overall sentiment KPIs.
     */
    public function executeHeader(FormTarget $target): array
    {
        $target->load(['form', 'semester', 'formVersion']);

        $courseInfo = null;
        if ($target->scope_type === 'course') {
            $offering = CourseOffering::with(['unit', 'lecture'])->find($target->scope_id);
            if ($offering) {
                $instructor = $offering->lecture
                    ? trim(($offering->lecture->title ? $offering->lecture->title.' ' : '').$offering->lecture->first_name.' '.$offering->lecture->last_name)
                    : null;
                $courseInfo = [
                    'code' => $offering->unit?->code,
                    'name' => $offering->unit?->name,
                    'section' => $offering->section_code,
                    'instructor' => $instructor,
                ];
            }
        }

        $assignments = DB::table('student_form_assignments')->where('form_target_id', $target->id)->get();
        $responsesTotal = $assignments->count();
        $responseIds = $assignments->whereNotNull('response_id')->pluck('response_id');
        $responsesDone = $responseIds->count();
        $responsePercent = $responsesTotal > 0 ? round(($responsesDone / $responsesTotal) * 100) : 0;

        $cfg = SurveyAggregateConfig::fromForm($target->form);

        $ratingAnswersQuery = Answer::query()
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->whereIn('answers.response_id', $responseIds)
            ->where('questions.type', 'rating')
            ->whereNotNull('answers.answer_number')
            ->where('answers.answer_number', '>', 0)
            ->select('answers.*');

        if ($cfg->questionCodes !== null) {
            $ratingAnswersQuery->whereIn('questions.code', $cfg->questionCodes);
        }

        $ratingAnswers = $ratingAnswersQuery->get();

        $includedCount = DB::table('questions')
            ->where('form_version_id', $target->form_version_id)
            ->where('type', 'rating')
            ->when($cfg->questionCodes !== null, fn ($q) => $q->whereIn('code', $cfg->questionCodes))
            ->count();

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
                ...$this->overallKpis($ratingAnswers, $cfg),
                'is_custom' => $cfg->isCustom,
                'positive_min' => $cfg->positiveMin,
                'negative_max' => $cfg->negativeMax,
                'included_count' => $includedCount,
                'configured_count' => $cfg->questionCodes !== null ? count($cfg->questionCodes) : $includedCount,
            ],
        ];
    }

    /**
     * @param  Collection<int, Answer>  $ratingAnswers
     * @return array{average: float, positive_percent: int, neutral_percent: int, negative_percent: int}
     */
    private function overallKpis(Collection $ratingAnswers, SurveyAggregateConfig $cfg): array
    {
        $overallTotal = $ratingAnswers->count();
        $overallAvg = $ratingAnswers->avg('answer_number');
        $overallPositive = $ratingAnswers->filter(fn ($a) => $cfg->isPositive((float) $a->answer_number))->count();
        $overallNegative = $ratingAnswers->filter(fn ($a) => $cfg->isNegative((float) $a->answer_number))->count();
        $overallNeutral = $overallTotal - $overallPositive - $overallNegative;

        return [
            'average' => $overallAvg !== null ? (float) round((float) $overallAvg, 1) : 0.0,
            'positive_percent' => $overallTotal > 0 ? (int) round(($overallPositive / $overallTotal) * 100) : 0,
            'neutral_percent' => $overallTotal > 0 ? (int) round(($overallNeutral / $overallTotal) * 100) : 0,
            'negative_percent' => $overallTotal > 0 ? (int) round(($overallNegative / $overallTotal) * 100) : 0,
        ];
    }

    /**
     * Get per-section / per-question chart data — heavy, always deferred.
     */
    public function executeSections(FormTarget $target): array
    {
        $target->loadMissing(['formVersion', 'form']);

        $assignments = DB::table('student_form_assignments')->where('form_target_id', $target->id)->get();
        $responseIds = $assignments->whereNotNull('response_id')->pluck('response_id');

        $version = $target->formVersion()->with(['sections.questions.options'])->first();
        if (! $version) {
            return ['sections' => []];
        }

        $cfg = SurveyAggregateConfig::fromForm($target->form);
        $allAnswers = Answer::whereIn('response_id', $responseIds)->with(['selectedOptions'])->get();

        $sections = [];
        foreach ($version->sections as $section) {
            $questionsData = [];
            $sectionRatingQuestionIds = $section->questions->where('type', 'rating')->pluck('id');
            $sectionRatingAnswers = $allAnswers->whereIn('question_id', $sectionRatingQuestionIds)->whereNotNull('answer_number');
            $sectionStats = null;

            if ($sectionRatingAnswers->isNotEmpty()) {
                $count = $sectionRatingAnswers->count();
                $sectionStats = [
                    'average' => (float) round($sectionRatingAnswers->avg('answer_number'), 1),
                    'positive_percent' => $count > 0 ? round(($sectionRatingAnswers->filter(fn ($a) => $cfg->isPositive((float) $a->answer_number))->count() / $count) * 100) : 0,
                    'negative_percent' => $count > 0 ? round(($sectionRatingAnswers->filter(fn ($a) => $cfg->isNegative((float) $a->answer_number))->count() / $count) * 100) : 0,
                    'distribution' => $this->getRatingDistribution($sectionRatingAnswers),
                ];
            }

            foreach ($section->questions as $question) {
                $qAnswers = $allAnswers->where('question_id', $question->id);
                $aggregation = $this->aggregateQuestion($question, $qAnswers);
                if ($aggregation) {
                    $questionsData[] = [
                        'id' => $question->id,
                        'text' => $question->text,
                        'type' => $question->type,
                        'order' => $question->order_index,
                        'total_responses' => $qAnswers->count(),
                        'data' => $aggregation,
                    ];
                }
            }

            if (count($questionsData) > 0) {
                $sections[] = [
                    'id' => $section->id,
                    'title' => $section->title,
                    'stats' => $sectionStats,
                    'questions' => $questionsData,
                ];
            }
        }

        return ['sections' => $sections];
    }

    private function aggregateQuestion($question, $answers)
    {
        if ($answers->isEmpty()) {
            return null;
        }

        switch ($question->type) {
            case 'rating':
                return [
                    'average' => (float) round($answers->avg('answer_number'), 1),
                    'distribution' => $this->getRatingDistribution($answers),
                ];

            case 'single_choice':
            case 'likert':
            case 'multi_choice':
                $optionCounts = [];
                // Initialize with 0 for all defined options
                foreach ($question->options as $opt) {
                    $optionCounts[$opt->id] = [
                        'label' => $opt->label,
                        'count' => 0,
                    ];
                }

                foreach ($answers as $ans) {
                    foreach ($ans->selectedOptions as $opt) {
                        if (isset($optionCounts[$opt->id])) {
                            $optionCounts[$opt->id]['count']++;
                        }
                    }
                }

                $total = $answers->count();

                $results = [];
                foreach ($optionCounts as $optData) {
                    $results[] = [
                        'label' => $optData['label'],
                        'count' => $optData['count'],
                        'percentage' => $total > 0 ? round(($optData['count'] / $total) * 100, 1) : 0,
                    ];
                }

                return ['options' => $results];

            case 'short_text':
            case 'long_text':
                // Return all text responses
                return [
                    'responses' => $answers->pluck('answer_text')->filter()->values()->all(),
                ];

            default:
                return null;
        }
    }

    /**
     * Helper to get 1-5 distribution counts.
     */
    private function getRatingDistribution($ratings): array
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
