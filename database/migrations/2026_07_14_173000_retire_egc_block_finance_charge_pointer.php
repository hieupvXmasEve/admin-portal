<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('egc_blocks', 'finance_charge_id')) {
            return;
        }

        $rows = DB::table('egc_blocks as block')
            ->join('finance_charges as charge', 'charge.id', '=', 'block.finance_charge_id')
            ->leftJoin('finance_obligations as obligation', 'obligation.id', '=', 'charge.finance_obligation_id')
            ->whereNotNull('block.finance_charge_id')
            ->select(['block.id as block_id', 'charge.finance_obligation_id'])
            ->orderBy('block.id')
            ->get();

        if ($rows->contains(static fn (object $row): bool => $row->finance_obligation_id === null)) {
            throw new RuntimeException(
                'Cannot retire egc_blocks.finance_charge_id: an EGC block has no canonical Finance obligation.'
            );
        }

        if ($rows->pluck('finance_obligation_id')->unique()->count() !== $rows->count()) {
            throw new RuntimeException(
                'Cannot retire egc_blocks.finance_charge_id: one Finance obligation maps to multiple EGC blocks.'
            );
        }

        foreach ($rows as $row) {
            $sourceRef = 'egc-block:'.$row->block_id;
            $conflict = DB::table('finance_obligations')
                ->where('source_system', 'finance')
                ->where('source_kind', 'egc_block')
                ->where('source_ref', $sourceRef)
                ->where('id', '!=', $row->finance_obligation_id)
                ->exists();
            if ($conflict) {
                throw new RuntimeException(
                    "Cannot retire egc_blocks.finance_charge_id: canonical source {$sourceRef} already belongs to another obligation."
                );
            }
        }

        foreach ($rows as $row) {
            DB::table('finance_obligations')
                ->where('id', $row->finance_obligation_id)
                ->update([
                    'source_system' => 'finance',
                    'source_kind' => 'egc_block',
                    'source_ref' => 'egc-block:'.$row->block_id,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('egc_blocks', function (Blueprint $table): void {
            $table->dropForeign(['finance_charge_id']);
            $table->dropColumn('finance_charge_id');
        });
    }

    /** Forward-only retirement: source blocks must not regain a ledger pointer. */
    public function down(): void {}
};
