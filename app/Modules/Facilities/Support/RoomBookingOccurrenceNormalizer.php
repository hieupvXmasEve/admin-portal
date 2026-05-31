<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use Carbon\Carbon;

class RoomBookingOccurrenceNormalizer
{
    public function normalize(array $data): array
    {
        $rawOccurrences = $data['occurrences'] ?? null;

        if (is_array($rawOccurrences) && count($rawOccurrences) > 0) {
            return collect($rawOccurrences)
                ->map(fn (array $occurrence) => [
                    'booking_date' => Carbon::parse($occurrence['booking_date'])->format('Y-m-d'),
                    'start_time' => substr((string) $occurrence['start_time'], 0, 5),
                    'end_time' => substr((string) $occurrence['end_time'], 0, 5),
                ])
                ->unique(fn (array $occurrence) => $occurrence['booking_date'].'|'.$occurrence['start_time'].'|'.$occurrence['end_time'])
                ->sortBy(['booking_date', 'start_time'])
                ->values()
                ->all();
        }

        return [[
            'booking_date' => Carbon::parse($data['booking_date'] ?? $data['date_from'])->format('Y-m-d'),
            'start_time' => substr((string) $data['start_time'], 0, 5),
            'end_time' => substr((string) $data['end_time'], 0, 5),
        ]];
    }

    public function recurrenceMetadata(array $occurrences): array
    {
        if (count($occurrences) <= 1) {
            return [
                'is_recurring' => false,
                'recurrence_type' => null,
                'recurrence_end_date' => null,
                'recurrence_days' => null,
            ];
        }

        $dates = collect($occurrences)->pluck('booking_date')->values();
        $first = Carbon::parse($dates->first());
        $last = Carbon::parse($dates->last());
        $spanDays = $first->diffInDays($last) + 1;
        $weekdays = $dates
            ->map(fn (string $date) => Carbon::parse($date)->format('l'))
            ->unique()
            ->values()
            ->all();

        return [
            'is_recurring' => true,
            'recurrence_type' => $dates->count() === $spanDays ? 'daily' : 'weekly',
            'recurrence_end_date' => $last->format('Y-m-d'),
            'recurrence_days' => $weekdays,
        ];
    }
}
