<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    private const string TABLE = 'egc_retake_discount_links';

    private const string LEGACY_POINTER = 'target_finance_charge_id';

    private const string LEGACY_UNIQUE = 'egc_retake_discount_links_target_finance_charge_id_unique';

    private const string TARGET_POINTER = 'target_invoice_line_id';

    private const string TARGET_UNIQUE = 'egc_retake_discount_links_target_invoice_line_id_unique';

    public function up(): void
    {
        $this->addTargetPointerIfMissing();

        if (Schema::hasColumn(self::TABLE, self::LEGACY_POINTER)) {
            $this->resolveCanonicalTargets();
            $this->dropLegacyPointer();
        }

        if (! Schema::hasIndex(self::TABLE, self::TARGET_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(self::TARGET_POINTER);
            });
        }

        $this->assertCanonicalSchema();
    }

    private function addTargetPointerIfMissing(): void
    {
        if (Schema::hasColumn(self::TABLE, self::TARGET_POINTER)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->foreignId(self::TARGET_POINTER)
                ->nullable()
                ->after('source_egc_block_id')
                ->constrained('invoice_lines')
                ->restrictOnDelete();
        });
    }

    private function resolveCanonicalTargets(): void
    {
        $links = DB::table(self::TABLE)->orderBy('id')->get(['id', 'invoice_discount_id']);
        foreach ($links as $link) {
            $netAllocations = DB::table('discount_allocations')
                ->where('invoice_discount_id', $link->invoice_discount_id)
                ->groupBy('invoice_line_id')
                ->orderBy('invoice_line_id')
                ->get([
                    'invoice_line_id',
                    DB::raw('SUM(amount) as net_amount'),
                ]);
            if ($netAllocations->isEmpty()) {
                throw new RuntimeException(
                    "Cannot retire EGC retake target charge pointer for link {$link->id}: no discount allocation evidence exists."
                );
            }

            if ($netAllocations->contains(
                static fn (object $allocation): bool => Money::vnd((string) $allocation->net_amount)->minor_amount < 0,
            )) {
                throw new RuntimeException(
                    "Cannot retire EGC retake target charge pointer for link {$link->id}: a discount allocation line has a negative net amount."
                );
            }

            $activeLineIds = $netAllocations
                ->filter(static fn (object $allocation): bool => Money::vnd((string) $allocation->net_amount)->minor_amount > 0)
                ->pluck('invoice_line_id')
                ->values();
            if ($activeLineIds->count() > 1) {
                throw new RuntimeException(
                    "Cannot retire EGC retake target charge pointer for link {$link->id}: multiple discount allocation lines remain active."
                );
            }

            DB::table(self::TABLE)
                ->where('id', $link->id)
                ->update([
                    self::TARGET_POINTER => $activeLineIds->first(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function dropLegacyPointer(): void
    {
        foreach (Schema::getForeignKeys(self::TABLE) as $foreignKey) {
            if ($foreignKey['columns'] !== [self::LEGACY_POINTER]) {
                continue;
            }

            Schema::table(self::TABLE, function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey['name']);
            });
        }

        if (Schema::hasIndex(self::TABLE, self::LEGACY_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE);
            });
        }

        if (Schema::hasColumn(self::TABLE, self::LEGACY_POINTER)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn(self::LEGACY_POINTER);
            });
        }
    }

    private function assertCanonicalSchema(): void
    {
        if (! Schema::hasColumn(self::TABLE, self::TARGET_POINTER)
            || Schema::hasColumn(self::TABLE, self::LEGACY_POINTER)
            || ! Schema::hasIndex(self::TABLE, self::TARGET_UNIQUE)) {
            throw new RuntimeException('Cannot retire EGC retake target charge pointer: canonical schema verification failed.');
        }
    }

    /** Forward-only retirement: the Finance-owned invoice-line audit link remains. */
    public function down(): void {}
};
