<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Admissions\Models;

use App\Models\StudentApplication;
use App\Modules\Admissions\Models\ApplicationAcademicScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAcademicScore>
 */
class ApplicationAcademicScoreFactory extends Factory
{
    protected $model = ApplicationAcademicScore::class;

    public function definition(): array
    {
        return [
            'student_application_id' => StudentApplication::factory(),
            'subject_code' => 'toan',
            'score' => fake()->randomFloat(2, 0, 10),
            'source' => ApplicationAcademicScore::SOURCE_SCHOOL_REPORT,
        ];
    }

    public function forApplication(StudentApplication $application): static
    {
        return $this->state(fn (array $attributes) => ['student_application_id' => $application->id]);
    }
}
