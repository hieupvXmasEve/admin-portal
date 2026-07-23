<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicSpaceOccupancy
{
    public function __construct(
        public string $type,
        public int $sourceId,
        public int $roomId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public string $title,
        public string $status,
        public ?int $reservationId = null,
        public ?string $description = null,
        public ?string $instructor = null,
        public ?int $courseOfferingId = null,
    ) {}

    /**
     * @return array{
     *   type: string,
     *   id: int,
     *   room_id: int,
     *   date: string,
     *   start_time: string,
     *   end_time: string,
     *   title: string,
     *   status: string,
     *   reservation_id: int|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->sourceId,
            'room_id' => $this->roomId,
            'date' => $this->date,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'title' => $this->title,
            'status' => $this->status,
            'reservation_id' => $this->reservationId,
        ];
    }
}
