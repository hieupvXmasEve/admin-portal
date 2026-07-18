<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngCampusMapping;
use Illuminate\Support\Facades\DB;

final class UpdateDngCampusMappingAction
{
    /**
     * @param  array{campus_id: int, provider_code: string, actor_user_id: int|null}  $data
     */
    public static function run(array $data): DngCampusMapping
    {
        return app(self::class)->handle(
            $data['campus_id'],
            $data['provider_code'],
            $data['actor_user_id'],
        );
    }

    private function handle(int $campusId, string $providerCode, ?int $actorUserId): DngCampusMapping
    {
        return DB::transaction(function () use ($campusId, $providerCode, $actorUserId): DngCampusMapping {
            $mapping = DngCampusMapping::query()->firstOrNew(['campus_id' => $campusId]);
            $mapping->provider_code = $providerCode;
            $mapping->updated_by_user_id = $actorUserId;

            if (! $mapping->exists) {
                $mapping->created_by_user_id = $actorUserId;
            }

            $mapping->save();

            return $mapping;
        });
    }
}
