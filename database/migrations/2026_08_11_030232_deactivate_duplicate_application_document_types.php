<?php

declare(strict_types=1);

use App\Modules\Upload\Models\ApplicationDocumentType;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time state fix for the three duplicate document-type codes
 * (`ApplicationDocumentType::RETIRED_CODES`). A fresh environment may run this
 * migration before the catalog rows exist (they arrive later from CSV/CRM) —
 * that is fine, `whereIn()` simply matches nothing. Durability comes from the
 * sync-level guard in `ApplicationDocumentTypeSyncService::sync()`, not this
 * migration; a re-synced catalog cannot reactivate these codes either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        ApplicationDocumentType::query()
            ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
            ->update(['active' => false]);
    }

    public function down(): void
    {
        ApplicationDocumentType::query()
            ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
            ->update(['active' => true]);
    }
};
