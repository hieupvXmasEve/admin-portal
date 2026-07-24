<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Support\CampusLogContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class BulkUpdateCourseOfferingSessionsAction
{
    /**
     * @param  array<int, int>  $sessionIds
     * @param  array<string, mixed>  $data
     */
    public static function run(CourseOffering $courseOffering, array $sessionIds, array $data): int
    {
        /** @var Collection<int, ClassSession> $sessions */
        $sessions = ClassSession::query()
            ->whereIn('id', $sessionIds)
            ->where('course_offering_id', $courseOffering->id)
            ->get();

        if ($sessions->count() !== count($sessionIds)) {
            throw new \DomainException('Some sessions do not belong to this course offering.');
        }

        [$count, $updateData] = DB::transaction(function () use ($data, $sessionIds, $sessions): array {
            $updateData = self::formatUpdateData($data);

            if (isset($updateData['start_time']) && isset($updateData['end_time'])) {
                $start = Carbon::createFromFormat('H:i:s', $updateData['start_time']);
                $end = Carbon::createFromFormat('H:i:s', $updateData['end_time']);
                $updateData['duration_minutes'] = $start->diffInMinutes($end);

                ClassSession::query()->whereIn('id', $sessionIds)->update($updateData);
            } elseif (isset($updateData['start_time']) || isset($updateData['end_time'])) {
                foreach ($sessions as $session) {
                    $sessionUpdateData = $updateData;
                    $sessionStart = $updateData['start_time'] ?? $session->start_time?->format('H:i:s');
                    $sessionEnd = $updateData['end_time'] ?? $session->end_time?->format('H:i:s');

                    if ($sessionStart !== null && $sessionEnd !== null) {
                        $start = Carbon::createFromFormat('H:i:s', $sessionStart);
                        $end = Carbon::createFromFormat('H:i:s', $sessionEnd);
                        $sessionUpdateData['duration_minutes'] = $start->diffInMinutes($end);
                    }

                    ClassSession::query()->whereKey($session->id)->update($sessionUpdateData);
                }
            } else {
                ClassSession::query()->whereIn('id', $sessionIds)->update($updateData);
            }

            return [$sessions->count(), $updateData];
        });

        self::logBulkUpdateActivity($courseOffering, $sessions, $updateData);

        return $count;
    }

    /** @param array<string, mixed> $data */
    private static function formatUpdateData(array $data): array
    {
        $updateData = [];

        foreach (['start_time', 'end_time'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field].':00';
            }
        }

        foreach (['lecture_id', 'room_id'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        return $updateData;
    }

    /** @param Collection<int, ClassSession> $sessions @param array<string, mixed> $updateData */
    private static function logBulkUpdateActivity(CourseOffering $courseOffering, Collection $sessions, array $updateData): void
    {
        $logName = CampusLogContext::getLogName('ClassSession', $courseOffering->campus_id);
        $properties = CampusLogContext::enhanceLogProperties([
            'operation' => 'bulk_update',
            'session_count' => $sessions->count(),
            'session_ids' => $sessions->pluck('id')->all(),
            'changed_fields' => array_keys($updateData),
            'update_data' => $updateData,
            'course_offering_id' => $courseOffering->id,
            'course_code' => $courseOffering->course_code,
        ], $courseOffering->campus_id);

        activity($logName)
            ->performedOn($courseOffering)
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->event('bulk_updated')
            ->log("Bulk updated {$sessions->count()} class session(s) for course offering {$courseOffering->course_code}");
    }
}
