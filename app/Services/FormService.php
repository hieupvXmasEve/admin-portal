<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\FormTarget;
use App\Models\Student;
use App\Models\Campus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FormService
{
    /**
     * Get available forms for a student.
     */
    public function getAvailableFormsForStudent(Student $student, ?Campus $campus = null): Collection
    {
        $now = now();
        $campusId = $campus ? $campus->id : $student->campus_id;

        // Get forms that are:
        // 1. Active
        // 2. Either have no targets (always visible) OR have targets for student's campus
        // 3. Within the time window (if targets exist)
        $forms = Form::active()
            ->with(['latestPublishedVersion.questions.options', 'targets', 'visibilityRoles'])
            ->where(function ($query) use ($campusId, $now) {
                // Forms without any targets are always visible
                $query->whereDoesntHave('targets')
                    // OR forms with targets that match the student's campus and time window
                    ->orWhereHas('targets', function ($targetQuery) use ($campusId, $now) {
                        $targetQuery->where(function ($q) use ($campusId) {
                            $q->whereNull('campus_id')
                                ->orWhere('campus_id', $campusId);
                        })
                            ->where('start_at', '<=', $now)
                            ->where(function ($q) use ($now) {
                                $q->whereNull('end_at')
                                    ->orWhere('end_at', '>=', $now);
                            });
                    });
            })
            ->get();

        // Filter forms based on submission limits, enrollment, etc.
        return $forms->filter(function ($form) use ($student, $campus) {
            return $this->canStudentSubmitForm($student, $form, $campus);
        });
    }


    /**
     * Check if a student is eligible to submit the given form at this time.
     */
    public function canStudentSubmitForm(Student $student, Form $form, ?Campus $campus = null): bool
    {
        $now = now();
        $campusId = $campus ? $campus->id : $student->campus_id;

        // If form has no targets, allow unlimited submissions
        $targetsCount = $form->targets()->count();

        if ($targetsCount === 0) {
            // For forms without targets, allow unlimited submissions
            return true;
        }

        // Filter only targets that apply to this student's campus and are open
        $targets = $form->targets()
            ->where(function ($query) use ($campusId) {
                $query->whereNull('campus_id')
                    ->orWhere('campus_id', $campusId);
            })
            ->where('start_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->get();

        foreach ($targets as $target) {
            // Count responses already submitted by this student for this exact scope
            $submissionCount = $form->responses()
                ->where('submitted_by_student_id', $student->id)
                ->where('campus_id', $campusId)
                ->where('target_scope_type', $target->scope_type)
                ->where('target_scope_id', $target->scope_id)
                ->count();

            if ($submissionCount < $target->submission_limit_per_user) {
                return true;
            }
        }

        return false;
    }


    /**
     * Create a new form with initial version.
     */
    public function createForm(array $data, array $versionData = []): Form
    {
        return DB::transaction(function () use ($data, $versionData) {
            // Create the form
            $form = Form::create($data);

            // Create initial version
            $version = $form->versions()->create(array_merge([
                'version_no' => 1,
                'is_published' => false,
                'effective_from' => now(),
            ], $versionData));

            return $form->load('versions');
        });
    }

    /**
     * Create a new version of a form.
     */
    public function createFormVersion(Form $form, array $data): FormVersion
    {
        $latestVersion = $form->versions()->orderBy('version_no', 'desc')->first();
        $newVersionNo = $latestVersion ? $latestVersion->version_no + 1 : 1;

        return $form->versions()->create(array_merge($data, [
            'version_no' => $newVersionNo,
        ]));
    }

    /**
     * Publish a form version.
     */
    public function publishVersion(FormVersion $version): FormVersion
    {
        return DB::transaction(function () use ($version) {
            // Unpublish other versions of the same form if needed
            $version->form->versions()
                ->where('id', '!=', $version->id)
                ->where('is_published', true)
                ->update(['is_published' => false]);

            // Publish this version
            $version->update([
                'is_published' => true,
                'effective_from' => $version->effective_from ?: now(),
            ]);

            return $version->fresh();
        });
    }

    /**
     * Create form targets for campus and scope.
     */
    public function createTarget(Form $form, array $data): FormTarget
    {
        // Use latest published version if not specified
        if (!isset($data['form_version_id'])) {
            $latestVersion = $form->latestPublishedVersion;
            if ($latestVersion) {
                $data['form_version_id'] = $latestVersion->id;
            }
        }

        return $form->targets()->create($data);
    }

    /**
     * Get form statistics for a campus.
     */
    public function getFormStatistics(Form $form, ?Campus $campus = null): array
    {
        $query = $form->responses();

        if ($campus) {
            $query->where('campus_id', $campus->id);
        }

        return [
            'total_responses' => $query->count(),
            'submitted' => $query->where('status', 'submitted')->count(),
            'approved' => $query->where('status', 'approved')->count(),
            'rejected' => $query->where('status', 'rejected')->count(),
            'pending_review' => $query->whereNull('reviewed_by_user_id')->count(),
            'anonymous_responses' => $query->where('anonymized', true)->count(),
        ];
    }

    /**
     * Create complete form with structure.
     */
    public function createCompleteForm(array $formData): Form
    {
        return DB::transaction(function () use ($formData) {
            // Create the form
            $form = Form::create([
                'code' => $formData['code'],
                'type' => $formData['type'],
                'title' => $formData['title'],
                'description' => $formData['description'] ?? null,
                'status' => $formData['status'] ?? 'draft',
                'created_by' => auth()->id(),
            ]);

            // Create initial version
            $version = $form->versions()->create([
                'version_no' => 1,
                'is_published' => $formData['publish_immediately'] ?? false,
                'effective_from' => $formData['effective_from'] ?? now(),
                'effective_to' => $formData['effective_to'] ?? null,
            ]);

            // Create sections if provided
            if (isset($formData['sections'])) {
                foreach ($formData['sections'] as $sectionIndex => $sectionData) {
                    $section = $version->sections()->create([
                        'title' => $sectionData['title'],
                        'description' => $sectionData['description'] ?? null,
                        'order_index' => $sectionIndex,
                    ]);

                    // Create questions in this section
                    if (isset($sectionData['questions'])) {
                        $this->createQuestionsForSection($version, $section, $sectionData['questions']);
                    }
                }
            }

            // Create questions without sections
            if (isset($formData['questions'])) {
                $this->createQuestionsForSection($version, null, $formData['questions']);
            }

            // Set visibility roles
            if (isset($formData['visibility_roles'])) {
                $form->visibilityRoles()->sync($formData['visibility_roles']);
            }

            // Set result visibility
            if (isset($formData['result_visibility'])) {
                foreach ($formData['result_visibility'] as $visibilityData) {
                    $form->resultVisibility()->create($visibilityData);
                }
            }

            // Create targets
            if (isset($formData['targets'])) {
                foreach ($formData['targets'] as $targetData) {
                    $targetData['form_version_id'] = $version->id;
                    $form->targets()->create($targetData);
                }
            }

            return $form->load([
                'versions.sections.questions.options',
                'versions.questions.options',
                'visibilityRoles',
                'resultVisibility',
                'targets'
            ]);
        });
    }

    /**
     * Create questions for a section or directly for version.
     */
    protected function createQuestionsForSection(FormVersion $version, $section, array $questions): void
    {
        foreach ($questions as $questionIndex => $questionData) {
            $question = $version->questions()->create([
                'section_id' => $section?->id,
                'code' => $questionData['code'],
                'text' => $questionData['text'],
                'type' => $questionData['type'],
                'is_required' => $questionData['is_required'] ?? false,
                'help_text' => $questionData['help_text'] ?? null,
                'order_index' => $questionIndex,
                'validation_json' => $questionData['validation_json'] ?? null,
                'visibility_condition_json' => $questionData['visibility_condition_json'] ?? null,
            ]);

            // Create options if provided
            if (isset($questionData['options'])) {
                foreach ($questionData['options'] as $optionIndex => $optionData) {
                    $question->options()->create([
                        'value' => $optionData['value'],
                        'label' => $optionData['label'],
                        'order_index' => $optionIndex,
                        'allows_free_text' => $optionData['allows_free_text'] ?? false,
                    ]);
                }
            }
        }
    }

    /**
     * Update form structure.
     */
    public function updateFormStructure(Form $form, array $data): Form
    {
        return DB::transaction(function () use ($form, $data) {
            // Update form basic info
            $form->update([
                'title' => $data['title'] ?? $form->title,
                'description' => $data['description'] ?? $form->description,
                'status' => $data['status'] ?? $form->status,
            ]);

            // If structure is being updated, create new version
            if (isset($data['sections']) || isset($data['questions'])) {
                // Unpublish all existing versions to ensure only one version is published
                $form->versions()
                    ->where('is_published', true)
                    ->update(['is_published' => false]);

                // Create new version and publish it
                $newVersion = $this->createFormVersion($form, [
                    'is_published' => true,
                    'effective_from' => $data['effective_from'] ?? now(),
                    'effective_to' => $data['effective_to'] ?? null,
                ]);

                // Create sections if provided
                if (isset($data['sections'])) {
                    foreach ($data['sections'] as $sectionIndex => $sectionData) {
                        $section = $newVersion->sections()->create([
                            'title' => $sectionData['title'],
                            'description' => $sectionData['description'] ?? null,
                            'order_index' => $sectionIndex,
                        ]);

                        // Create questions in this section
                        if (isset($sectionData['questions'])) {
                            $this->createQuestionsForSection($newVersion, $section, $sectionData['questions']);
                        }
                    }
                }

                // Create questions without sections
                if (isset($data['questions'])) {
                    $this->createQuestionsForSection($newVersion, null, $data['questions']);
                }
            }

            // Update visibility roles
            if (isset($data['visibility_roles'])) {
                $form->visibilityRoles()->sync($data['visibility_roles']);
            }

            // Update result visibility
            if (isset($data['result_visibility'])) {
                $form->resultVisibility()->delete();
                foreach ($data['result_visibility'] as $visibilityData) {
                    $form->resultVisibility()->create($visibilityData);
                }
            }


            return $form->load([
                'versions.sections.questions.options',
                'versions.questions.options',
                'visibilityRoles',
                'resultVisibility'
            ]);
        });
    }

    /**
     * Delete a form and all related data.
     */
    public function deleteForm(Form $form): bool
    {
        return DB::transaction(function () use ($form) {
            // Delete all versions and their related data
            foreach ($form->versions as $version) {
                // Delete questions and their options
                foreach ($version->questions as $question) {
                    $question->options()->delete();
                    $question->delete();
                }

                // Delete sections
                $version->sections()->delete();
                $version->delete();
            }

            // Delete visibility settings
            $form->visibilityRoles()->detach();
            $form->resultVisibility()->delete();

            // Delete targets
            $form->targets()->delete();

            // Delete the form
            return $form->delete();
        });
    }

    /**
     * Get forms for admin listing.
     */
    public function getFormsForAdmin(array $filters = []): Collection
    {
        $query = Form::with([
            'creator',
            'latestPublishedVersion',
            'visibilityRoles',
            'targets'
        ]);

        // Apply filters
        if (isset($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['campus_id']) && $filters['campus_id'] !== 'all') {
            $query->whereHas('targets', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id'])
                    ->orWhereNull('campus_id');
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Clone a form with all its components.
     */
    public function cloneForm(Form $form, string $newCode, string $newTitle): Form
    {
        return DB::transaction(function () use ($form, $newCode, $newTitle) {
            // Clone the form
            $newForm = $form->replicate();
            $newForm->code = $newCode;
            $newForm->title = $newTitle;
            $newForm->status = 'draft';
            $newForm->save();

            // Clone the latest version
            $latestVersion = $form->latestPublishedVersion ?: $form->versions()->latest()->first();

            if ($latestVersion) {
                $newVersion = $latestVersion->replicate();
                $newVersion->form_id = $newForm->id;
                $newVersion->version_no = 1;
                $newVersion->is_published = false;
                $newVersion->save();

                // Clone sections and questions
                foreach ($latestVersion->sections as $section) {
                    $newSection = $section->replicate();
                    $newSection->form_version_id = $newVersion->id;
                    $newSection->save();

                    // Clone questions in this section
                    foreach ($section->questions as $question) {
                        $newQuestion = $question->replicate();
                        $newQuestion->form_version_id = $newVersion->id;
                        $newQuestion->section_id = $newSection->id;
                        $newQuestion->save();

                        // Clone options
                        foreach ($question->options as $option) {
                            $newOption = $option->replicate();
                            $newOption->question_id = $newQuestion->id;
                            $newOption->save();
                        }
                    }
                }

                // Clone questions without sections
                foreach ($latestVersion->questions()->whereNull('section_id')->get() as $question) {
                    $newQuestion = $question->replicate();
                    $newQuestion->form_version_id = $newVersion->id;
                    $newQuestion->save();

                    // Clone options
                    foreach ($question->options as $option) {
                        $newOption = $option->replicate();
                        $newOption->question_id = $newQuestion->id;
                        $newOption->save();
                    }
                }
            }

            // Clone visibility roles
            foreach ($form->visibilityRoles as $role) {
                $newForm->visibilityRoles()->attach($role->id);
            }

            // Clone result visibility
            foreach ($form->resultVisibility as $visibility) {
                $newVisibility = $visibility->replicate();
                $newVisibility->form_id = $newForm->id;
                $newVisibility->save();
            }

            return $newForm->load(['versions.questions.options', 'visibilityRoles']);
        });
    }
}
