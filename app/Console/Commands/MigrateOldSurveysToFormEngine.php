<?php

namespace App\Console\Commands;

use App\Models\FormSurvey;
use App\Models\StudentFormSurvey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOldSurveysToFormEngine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:old-surveys {--dry-run : Whether to run in dry-run mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from old form_surveys and student_form_surveys to new Form Engine tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY-RUN MODE: No changes will be saved to the database.');
        }

        // 1. Cleanup existing assignments and targets only
        // We keep responses, answers, etc. as they are now production-like data
        if (!$dryRun) {
            $this->info('Cleaning up existing assignments and targets...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('student_form_assignments')->truncate();
            DB::table('form_targets')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info('Cleanup completed.');
        }

        // 2. Fetch all old surveys
        $formSurveys = FormSurvey::with('courseOffering.semester')->get();
        $this->info("Found {$formSurveys->count()} old form surveys to migrate.");

        $bar = $this->output->createProgressBar($formSurveys->count());
        $bar->start();

        foreach ($formSurveys as $formSurvey) {
            $courseOffering = $formSurvey->courseOffering;

            if (!$courseOffering) {
                $this->warn("\nSurvey ID {$formSurvey->id} has no course offering. Skipping.");
                $bar->advance();
                continue;
            }

            DB::transaction(function () use ($formSurvey, $courseOffering, $dryRun, $bar) {
                // 3. Create FormTarget (Course level)
                $formTargetData = [
                    'form_id' => $formSurvey->form_id,
                    'form_version_id' => $formSurvey->form_version_id,
                    'campus_id' => $courseOffering->campus_id,
                    'semester_id' => $courseOffering->semester_id,
                    'scope_type' => 'course',
                    'scope_id' => $courseOffering->id,
                    'start_at' => $courseOffering->registration_start_date ?? $formSurvey->created_at,
                    'end_at' => $courseOffering->semester->end_date ?? null,
                    'is_mandatory' => true,
                    'status' => 'active',
                    'submission_limit_per_user' => 1,
                    'created_at' => $formSurvey->created_at,
                    'updated_at' => $formSurvey->updated_at,
                ];

                $formTargetId = 0;
                if (!$dryRun) {
                    $formTargetId = DB::table('form_targets')->insertGetId($formTargetData);
                }

                // 4. Map student surveys for this specific course
                $studentSurveys = StudentFormSurvey::where('form_survey_id', $formSurvey->id)->get();

                foreach ($studentSurveys as $studentSurvey) {
                    $assignmentData = [
                        'student_id' => $studentSurvey->student_id,
                        'form_target_id' => $formTargetId,
                        'status' => $studentSurvey->status === 'completed' ? 'completed' : 'not_started',
                        'response_id' => $studentSurvey->response_id,
                        'completed_at' => $studentSurvey->completed_at,
                        'created_at' => $studentSurvey->created_at,
                        'updated_at' => $studentSurvey->updated_at,
                    ];

                    if (!$dryRun) {
                        DB::table('student_form_assignments')->insert($assignmentData);
                    }
                }
                $bar->advance();
            });
        }

        $bar->finish();
        $this->info("\nMigration completed.");
    }
}
