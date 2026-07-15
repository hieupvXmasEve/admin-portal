<?php

declare(strict_types=1);

use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function egcRetakeTargetPointerRetirementMigration(): Migration
{
    return require database_path('migrations/2026_07_14_174000_retire_egc_retake_target_charge_pointer.php');
}

function restoreLegacyEgcRetakeTargetPointer(): void
{
    if (Schema::hasIndex('egc_retake_discount_links', 'egc_retake_discount_links_target_invoice_line_id_unique')) {
        Schema::table('egc_retake_discount_links', function (Blueprint $table): void {
            $table->dropUnique('egc_retake_discount_links_target_invoice_line_id_unique');
        });
    }

    Schema::table('egc_retake_discount_links', function (Blueprint $table): void {
        $table->foreignId('target_finance_charge_id')->nullable()->constrained('finance_charges')->cascadeOnDelete();
        $table->unique('target_finance_charge_id');
    });
}

/**
 * @return array{discount: InvoiceDiscount, firstLine: InvoiceLine, secondLine: InvoiceLine, sourceBlock: EgcBlock}
 */
function createReversedEgcRetakeAllocationHistory(): array
{
    $student = Student::factory()->create(['intake' => 1]);
    $semester = Semester::factory()->create();
    $sourceBlock = EgcBlock::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 3,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'EGC-RETAKE-MIGRATION-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'currency' => 'VND',
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $lines = collect([1, 2])->map(function (int $sequence) use ($student, $semester, $invoice): InvoiceLine {
        $charge = FinanceCharge::query()->create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 7_500_000,
            'description' => "EGC retake target {$sequence}",
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_VOID,
        ]);

        return InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
            'status' => 'void',
        ]);
    });

    $discount = InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'egc_retake',
        'discount_source' => EgcBlock::class,
        'description' => 'Reversed EGC retake discount',
        'amount' => 7_500_000,
        'status' => 'active',
        'reference_id' => $sourceBlock->id,
    ]);

    foreach ($lines as $line) {
        DiscountAllocation::query()->create([
            'invoice_discount_id' => $discount->id,
            'invoice_line_id' => $line->id,
            'amount' => 7_500_000,
            'entry_type' => 'allocation',
            'allocation_rule' => 'egc_retake_target',
        ]);
        DiscountAllocation::query()->create([
            'invoice_discount_id' => $discount->id,
            'invoice_line_id' => $line->id,
            'amount' => -7_500_000,
            'entry_type' => 'reversal',
            'allocation_rule' => 'egc_retake_target',
        ]);
    }

    DB::table('egc_retake_discount_links')->insert([
        'invoice_discount_id' => $discount->id,
        'source_egc_block_id' => $sourceBlock->id,
        'target_invoice_line_id' => null,
        'target_finance_charge_id' => $lines->first()->charge_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'discount' => $discount,
        'firstLine' => $lines->get(0),
        'secondLine' => $lines->get(1),
        'sourceBlock' => $sourceBlock,
    ];
}

it('resumes from a partial schema and preserves fully reversed EGC retake history', function () {
    restoreLegacyEgcRetakeTargetPointer();
    ['discount' => $discount] = createReversedEgcRetakeAllocationHistory();

    egcRetakeTargetPointerRetirementMigration()->up();
    egcRetakeTargetPointerRetirementMigration()->up();

    expect(Schema::hasColumn('egc_retake_discount_links', 'target_finance_charge_id'))->toBeFalse()
        ->and(Schema::hasColumn('egc_retake_discount_links', 'target_invoice_line_id'))->toBeTrue()
        ->and(Schema::hasIndex('egc_retake_discount_links', 'egc_retake_discount_links_target_invoice_line_id_unique'))->toBeTrue()
        ->and(DB::table('egc_retake_discount_links')->where('invoice_discount_id', $discount->id)->value('target_invoice_line_id'))->toBeNull()
        ->and(DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->count())->toBe(4)
        ->and((float) DiscountAllocation::query()->where('invoice_discount_id', $discount->id)->sum('amount'))->toBe(0.0);
});
