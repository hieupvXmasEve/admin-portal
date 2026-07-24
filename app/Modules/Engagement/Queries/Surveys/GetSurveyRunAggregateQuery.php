<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries\Surveys;

use App\Models\Answer;
use App\Models\CourseOffering;
use App\Models\FormTarget;
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

        // Overall rating KPIs
        $ratingAnswers = Answer::whereIn('response_id', $responseIds)->whereNotNull('answer_number')->where('answer_number', '>', 0)->get();
        $overallTotal = $ratingAnswers->count();
        $overallAvg = $ratingAnswers->avg('answer_number');
        $overallPositive = $ratingAnswers->where('answer_number', '>=', 4)->count();
        $overallNeutral = $ratingAnswers->where('answer_number', 3)->count();
        $overallNegative = $ratingAnswers->where('answer_number', '<=', 2)->count();

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
        ];
    }

    /**
     * Get per-section / per-question chart data — heavy, always deferred.
     */
    public function executeSections(FormTarget $target): array
    {
        $target->loadMissing(['formVersion']);

        $assignments = DB::table('student_form_assignments')->where('form_target_id', $target->id)->get();
        $responseIds = $assignments->whereNotNull('response_id')->pluck('response_id');

        $version = $target->formVersion()->with(['sections.questions.options'])->first();
        if (! $version) {
            return ['sections' => []];
        }

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
                    'positive_percent' => $count > 0 ? round(($sectionRatingAnswers->where('answer_number', '>=', 4)->count() / $count) * 100) : 0,
                    'negative_percent' => $count > 0 ? round(($sectionRatingAnswers->where('answer_number', '<=', 2)->count() / $count) * 100) : 0,
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

    /**
     * Full execute (kept for backward compatibility).
     *
     * @deprecated Prefer executeHeader() + executeSections() separately.
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

        // 2. Response counts
        $assignments = DB::table('student_form_assignments')
            ->where('form_target_id', $target->id)
            ->get();

        $responsesTotal = $assignments->count();
        $responseIds = $assignments->whereNotNull('response_id')->pluck('response_id');
        $responsesDone = $responseIds->count();
        $responsePercent = $responsesTotal > 0 ? round(($responsesDone / $responsesTotal) * 100) : 0;

        // 3. Load Version with Sections and Questions
        $version = $target->formVersion()->with(['sections.questions.options'])->first();
        if (! $version) {
            return [];
        }

        // 4. Fetch All Answers
        // We fetch all answers for these responses to aggregate locally
        $allAnswers = Answer::whereIn('response_id', $responseIds)
            ->with(['selectedOptions'])
            ->get();

        // 5. Calculate Overall Rating Stats (KPIs)
        // Filter only rating answers for the global KPI
        $ratingAnswers = $allAnswers->whereNotNull('answer_number')->where('answer_number', '>', 0);

        $overallAvg = $ratingAnswers->avg('answer_number');
        $overallTotal = $ratingAnswers->count();
        $overallPositive = $ratingAnswers->where('answer_number', '>=', 4)->count();
        $overallNeutral = $ratingAnswers->where('answer_number', 3)->count();
        $overallNegative = $ratingAnswers->where('answer_number', '<=', 2)->count();

        // 6. Aggregate by Section -> Question
        $sections = [];

        foreach ($version->sections as $section) {
            $questionsData = [];

            // Section-level rating stats (only if section has rating questions)
            $sectionRatingQuestionIds = $section->questions->where('type', 'rating')->pluck('id');
            $sectionRatingAnswers = $allAnswers->whereIn('question_id', $sectionRatingQuestionIds)->whereNotNull('answer_number');
            $sectionStats = null;

            if ($sectionRatingAnswers->isNotEmpty()) {
                $count = $sectionRatingAnswers->count();
                $sectionStats = [
                    'average' => (float) round($sectionRatingAnswers->avg('answer_number'), 1),
                    'positive_percent' => $count > 0 ? round(($sectionRatingAnswers->where('answer_number', '>=', 4)->count() / $count) * 100) : 0,
                    'negative_percent' => $count > 0 ? round(($sectionRatingAnswers->where('answer_number', '<=', 2)->count() / $count) * 100) : 0,
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

            // Only add section if it has questions
            if (count($questionsData) > 0) {
                $sections[] = [
                    'id' => $section->id,
                    'title' => $section->title,
                    'stats' => $sectionStats, // Can be null if no rating questions
                    'questions' => $questionsData,
                ];
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
        ];
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
