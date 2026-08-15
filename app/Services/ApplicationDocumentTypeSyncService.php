<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Upload\Models\ApplicationDocumentType;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the admissions CRM's document-type catalog into Swinx (ADR-0004).
 *
 * Sync is idempotent by `code` (the CRM-owned key): re-sending the same catalog
 * updates existing rows in place and never duplicates them. The CRM remains the
 * source of truth; the actual HTTP fetch belongs to the ingestion slice — this
 * service takes already-decoded catalog rows so it is callable from ingestion,
 * a seeder, or a console command alike.
 */
class ApplicationDocumentTypeSyncService
{
    /**
     * Upsert each catalog entry by `code`. Unknown keys are ignored; missing
     * optional keys fall back to the model defaults.
     *
     * @param  iterable<array<string, mixed>>  $catalog
     * @return int the number of catalog entries processed
     */
    public function sync(iterable $catalog): int
    {
        return DB::transaction(function () use ($catalog): int {
            $processed = 0;

            foreach ($catalog as $entry) {
                $code = $entry['code'] ?? null;

                if ($code === null || $code === '') {
                    continue;
                }

                $attributes = $this->attributesFrom($entry);

                if (in_array((string) $code, ApplicationDocumentType::RETIRED_CODES, true)) {
                    $attributes['active'] = false;
                }

                ApplicationDocumentType::query()->updateOrCreate(
                    ['code' => (string) $code],
                    $attributes,
                );

                $processed++;
            }

            return $processed;
        });
    }

    /**
     * Project a raw catalog row onto the fillable columns, dropping the key.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function attributesFrom(array $entry): array
    {
        $allowed = ['name', 'type', 'required', 'int_required', 'active', 'order', 'step'];

        return array_intersect_key($entry, array_flip($allowed));
    }
}
