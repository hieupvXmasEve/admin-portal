<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CourseOfferingUnitReference
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $credit_points,
        public ?int $level,
        public ?string $unit_type,
        /** @var array<string, mixed> */
        public array $payload = [],
    ) {}

    /**
     * @return array{unit_id: int, code: string, name: string, credit_points: string, level: int|null, unit_type: string|null}
     */
    public function toArray(): array
    {
        if ($this->payload !== []) {
            return $this->payload;
        }

        return [
            'unit_id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'credit_points' => $this->credit_points,
            'level' => $this->level,
            'unit_type' => $this->unit_type,
        ];
    }
}
