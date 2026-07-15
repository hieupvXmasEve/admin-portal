<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    private const string BLOCKS_TABLE = 'egc_blocks';

    private const string CHARGES_TABLE = 'finance_charges';

    private const string OBLIGATIONS_TABLE = 'finance_obligations';

    private const string POINTER_COLUMN = 'finance_charge_id';

    private const string POINTER_INDEX = 'egc_blocks_finance_charge_id_index';

    public function up(): void
    {
        $this->assertSupportedSchema();

        if (! Schema::hasColumn(self::BLOCKS_TABLE, self::POINTER_COLUMN)) {
            $this->assertPointerRetired();

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

        $this->dropPointerForeignKeyIfPresent();
        $this->dropPointerIndexIfPresent();

        if (Schema::hasColumn(self::BLOCKS_TABLE, self::POINTER_COLUMN)) {
            Schema::table(self::BLOCKS_TABLE, function (Blueprint $table): void {
                $table->dropColumn(self::POINTER_COLUMN);
            });
        }

        $this->assertPointerRetired();
    }

    private function assertSupportedSchema(): void
    {
        if (! Schema::hasTable(self::BLOCKS_TABLE)) {
            throw new RuntimeException(
                'Cannot retire egc_blocks.finance_charge_id: egc_blocks table is missing; this is not a supported EGC cutover schema.',
            );
        }

        if (! Schema::hasTable(self::CHARGES_TABLE) || ! Schema::hasTable(self::OBLIGATIONS_TABLE)) {
            throw new RuntimeException(
                'Cannot retire egc_blocks.finance_charge_id: canonical Finance tables are missing; this is not a supported EGC cutover schema.',
            );
        }
    }

    private function dropPointerForeignKeyIfPresent(): void
    {
        foreach ($this->pointerForeignKeyNames() as $foreignKeyName) {
            Schema::table(self::BLOCKS_TABLE, function (Blueprint $table) use ($foreignKeyName): void {
                $table->dropForeign($foreignKeyName);
            });
        }
    }

    /** @return list<string> */
    private function pointerForeignKeyNames(): array
    {
        $foreignKeys = array_values(array_filter(
            Schema::getForeignKeys(self::BLOCKS_TABLE),
            static fn (array $foreignKey): bool => $foreignKey['columns'] === [self::POINTER_COLUMN],
        ));

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['foreign_table'] !== self::CHARGES_TABLE || $foreignKey['foreign_columns'] !== ['id']) {
                throw new RuntimeException(
                    'Cannot retire egc_blocks.finance_charge_id: the source pointer has an unexpected foreign key target.',
                );
            }
        }

        return array_column($foreignKeys, 'name');
    }

    private function dropPointerIndexIfPresent(): void
    {
        if (! Schema::hasIndex(self::BLOCKS_TABLE, self::POINTER_INDEX)) {
            return;
        }

        Schema::table(self::BLOCKS_TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::POINTER_INDEX);
        });
    }

    private function assertPointerRetired(): void
    {
        if (Schema::hasColumn(self::BLOCKS_TABLE, self::POINTER_COLUMN)) {
            throw new RuntimeException('Cannot retire egc_blocks.finance_charge_id: the source pointer column still exists after cutover.');
        }

        if ($this->pointerForeignKeyNames() !== []) {
            throw new RuntimeException('Cannot retire egc_blocks.finance_charge_id: the source pointer foreign key still exists after cutover.');
        }

        if (Schema::hasIndex(self::BLOCKS_TABLE, [self::POINTER_COLUMN])) {
            throw new RuntimeException('Cannot retire egc_blocks.finance_charge_id: the source pointer index still exists after cutover.');
        }
    }

    /**
     * Forward-only retirement: source blocks must not regain a ledger pointer.
     * A rollback requires restoring the pre-cutover application release.
     */
    public function down(): void {}
};
