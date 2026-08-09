<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Upload\Models;

use App\Models\StudentApplication;
use App\Modules\Upload\Models\ApplicationDocument;
use App\Modules\Upload\Models\ApplicationDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    public function definition(): array
    {
        $name = fake()->word().'.'.fake()->randomElement(['jpg', 'png', 'pdf']);

        return [
            'student_application_id' => StudentApplication::factory(),
            'crm_file_id' => fake()->unique()->numerify('file-######'),
            'file_type_code' => fake()->bothify('doc_???_##'),
            'file_type_name' => fake()->words(2, true),
            'page_index' => 0,
            'original_name' => $name,
            'link' => fake()->url(),
            'mime_type' => fake()->randomElement(['image/jpeg', 'image/png', 'application/pdf']),
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'status' => 'active',
        ];
    }

    /**
     * Attach this document to a specific Application.
     */
    public function forApplication(StudentApplication $application): static
    {
        return $this->state(fn (array $attributes) => [
            'student_application_id' => $application->id,
        ]);
    }

    /**
     * Tag this document with a given catalog type (accepts a code or a model).
     */
    public function ofType(ApplicationDocumentType|string $type): static
    {
        $code = $type instanceof ApplicationDocumentType ? $type->code : $type;
        $name = $type instanceof ApplicationDocumentType ? $type->name : null;

        return $this->state(fn (array $attributes) => array_filter([
            'file_type_code' => $code,
            'file_type_name' => $name,
        ], fn ($value) => $value !== null));
    }

    /**
     * A document reference with no CRM origin (manually entered by staff).
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'crm_file_id' => null,
        ]);
    }
}
