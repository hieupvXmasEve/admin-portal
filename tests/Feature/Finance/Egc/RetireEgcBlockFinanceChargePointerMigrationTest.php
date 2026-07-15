<?php

declare(strict_types=1);

use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

uses(RefreshDatabase::class);

function egcPointerRetirementMigration(): Migration
{
    return require database_path('migrations/2026_07_14_173000_retire_egc_block_finance_charge_pointer.php');
}

function restoreLegacyEgcPointerSchema(): void
{
    Schema::table('egc_blocks', function (Blueprint $table): void {
        $table->unsignedBigInteger('finance_charge_id')->nullable();
    });

    Schema::table('egc_blocks', function (Blueprint $table): void {
        $table->foreign('finance_charge_id')
            ->references('id')
            ->on('finance_charges')
            ->nullOnDelete();
        $table->index('finance_charge_id');
    });
}

/**
 * @return array{block: EgcBlock, obligation: FinanceObligation, charge: FinanceCharge, line: InvoiceLine}
 */
function createLegacyEgcPointerEvidence(): array
{
    $student = Student::factory()->create(['intake' => 1]);
    $semester = Semester::factory()->create();
    $block = EgcBlock::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 1,
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 1 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'finance',
        'source_kind' => 'legacy_egc_block',
        'source_ref' => 'legacy-egc-block:'.$block->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 15_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test:egc-pointer-retirement',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge->update(['finance_obligation_id' => $obligation->id]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'EGC-POINTER-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);

    DB::table('egc_blocks')
        ->where('id', $block->id)
        ->update(['finance_charge_id' => $charge->id]);

    return compact('block', 'obligation', 'charge', 'line');
}

function removeLegacyEgcPointerForeignKey(): void
{
    Schema::table('egc_blocks', function (Blueprint $table): void {
        $table->dropForeign(['finance_charge_id']);
    });
}

function removeLegacyEgcPointerIndex(): void
{
    Schema::table('egc_blocks', function (Blueprint $table): void {
        $table->dropIndex('egc_blocks_finance_charge_id_index');
    });
}

function hasLegacyEgcPointerForeignKey(): bool
{
    return collect(Schema::getForeignKeys('egc_blocks'))
        ->contains(static fn (array $foreignKey): bool => $foreignKey['columns'] === ['finance_charge_id']);
}

function assertEgcPointerIsRetired(): void
{
    expect(Schema::hasColumn('egc_blocks', 'finance_charge_id'))->toBeFalse()
        ->and(hasLegacyEgcPointerForeignKey())->toBeFalse()
        ->and(Schema::hasIndex('egc_blocks', ['finance_charge_id']))->toBeFalse();
}

it('retires a clean pre-cutover EGC pointer without deleting historical evidence', function () {
    restoreLegacyEgcPointerSchema();
    ['block' => $block, 'obligation' => $obligation, 'charge' => $charge, 'line' => $line] = createLegacyEgcPointerEvidence();

    egcPointerRetirementMigration()->up();
    egcPointerRetirementMigration()->up();

    assertEgcPointerIsRetired();
    expect(EgcBlock::query()->find($block->id))->not->toBeNull()
        ->and($obligation->fresh()->source_system)->toBe('finance')
        ->and($obligation->fresh()->source_kind)->toBe('egc_block')
        ->and($obligation->fresh()->source_ref)->toBe('egc-block:'.$block->id)
        ->and(FinanceCharge::query()->find($charge->id))->not->toBeNull()
        ->and(InvoiceLine::query()->find($line->id))->not->toBeNull();
});

it('is a no-op when the EGC pointer has already been retired', function () {
    egcPointerRetirementMigration()->up();

    assertEgcPointerIsRetired();
});

it('completes after a deploy stopped after the foreign key step', function () {
    restoreLegacyEgcPointerSchema();
    createLegacyEgcPointerEvidence();
    removeLegacyEgcPointerForeignKey();

    egcPointerRetirementMigration()->up();

    assertEgcPointerIsRetired();
});

it('completes after a deploy stopped after the index step', function () {
    restoreLegacyEgcPointerSchema();
    createLegacyEgcPointerEvidence();
    removeLegacyEgcPointerForeignKey();
    removeLegacyEgcPointerIndex();

    egcPointerRetirementMigration()->up();

    assertEgcPointerIsRetired();
});

it('fails clearly for an unknown schema without egc blocks', function () {
    Schema::dropIfExists('egc_retake_discount_links');
    Schema::drop('egc_blocks');

    expect(fn () => egcPointerRetirementMigration()->up())
        ->toThrow(RuntimeException::class, 'egc_blocks table is missing');
});
