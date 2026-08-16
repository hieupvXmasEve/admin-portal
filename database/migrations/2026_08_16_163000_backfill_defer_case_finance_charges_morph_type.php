<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `DeferCase` moved from `App\Models` to `App\Modules\Finance\Models`
 * (phase 6, plan 260726-0333-zero-migration-debt-closure). `finance_charges`
 * stores its polymorphic `source_type` as a raw FQCN (no morphMap entry),
 * so any historical defer-credit charge row still carries the pre-move
 * class string. Rewrite it forward so `DeferCase::creditCharge()` keeps
 * matching those rows under the new namespace.
 */
return new class extends Migration
{
    private const OLD_FQCN = 'App\Models\DeferCase';

    private const NEW_FQCN = 'App\Modules\Finance\Models\DeferCase';

    public function up(): void
    {
        DB::table('finance_charges')
            ->where('source_type', self::OLD_FQCN)
            ->update(['source_type' => self::NEW_FQCN]);
    }

    public function down(): void
    {
        DB::table('finance_charges')
            ->where('source_type', self::NEW_FQCN)
            ->update(['source_type' => self::OLD_FQCN]);
    }
};
