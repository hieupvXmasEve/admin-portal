<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Engagement\DTO;

final readonly class MandatoryFormAssignment
{
    public function __construct(
        public int $id,
        public ?int $formId,
        public ?int $formVersionId,
        public ?string $formTitle,
        public ?string $originalFormTitle,
        public ?string $formType,
        public ?string $targetTitle,
        public ?string $targetType,
        public ?int $targetId,
        public mixed $dueDate,
        public string $status,
        public bool $isMandatory,
        public ?string $courseCode,
        public ?string $sectionCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->formId,
            'form_version_id' => $this->formVersionId,
            'form_title' => $this->formTitle,
            'original_form_title' => $this->originalFormTitle,
            'form_type' => $this->formType,
            'target_title' => $this->targetTitle,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'due_date' => $this->dueDate,
            'status' => $this->status,
            'is_mandatory' => $this->isMandatory,
            'course_code' => $this->courseCode,
            'section_code' => $this->sectionCode,
        ];
    }
}
