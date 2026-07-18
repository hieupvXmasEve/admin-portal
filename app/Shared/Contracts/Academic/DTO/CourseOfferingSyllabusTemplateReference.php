<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CourseOfferingSyllabusTemplateReference
{
    /**
     * @param  array{id: int, code: string, name: string}|null  $unit
     * @param  array{id: int, name: string}|null  $applicable_campus
     * @param  array{id: int, name: string}|null  $applicable_program
     */
    public function __construct(
        public int $id,
        public int $unit_id,
        public ?string $title,
        public ?string $version,
        public ?string $description,
        public ?string $delivery_mode,
        public ?array $unit,
        public ?array $applicable_campus,
        public ?array $applicable_program,
    ) {}

    /**
     * @return array{id: int, unit_id: int, title: string|null, version: string|null, description: string|null, delivery_mode: string|null, unit: array{id: int, code: string, name: string}|null, applicable_campus: array{id: int, name: string}|null, applicable_program: array{id: int, name: string}|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
            'delivery_mode' => $this->delivery_mode,
            'unit' => $this->unit,
            'applicable_campus' => $this->applicable_campus,
            'applicable_program' => $this->applicable_program,
        ];
    }
}
