<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $this->retirePointer(
            table: 'course_retake_registrations',
            sourceKind: 'course_retake_registration',
            sourcePrefix: 'retake:',
        );
        $this->retirePointer(
            table: 'exam_resit_attempts',
            sourceKind: 'exam_resit_attempt',
            sourcePrefix: 'exam-resit:',
        );
    }

    private function retirePointer(string $table, string $sourceKind, string $sourcePrefix): void
    {
        if (! Schema::hasColumn($table, 'finance_charge_id')) {
            return;
        }

        $invalidPointer = DB::table("{$table} as source")
            ->leftJoin('finance_charges as charge', 'charge.id', '=', 'source.finance_charge_id')
            ->leftJoin('finance_obligations as obligation', 'obligation.id', '=', 'charge.finance_obligation_id')
            ->whereNotNull('source.finance_charge_id')
            ->where(function ($query) use ($sourceKind, $sourcePrefix): void {
                $query->whereNull('charge.id')
                    ->orWhereNull('obligation.id')
                    ->orWhereNull('obligation.source_system')
                    ->orWhere('obligation.source_system', '!=', 'academic')
                    ->orWhereNull('obligation.source_kind')
                    ->orWhere('obligation.source_kind', '!=', $sourceKind)
                    ->orWhereNull('obligation.source_ref')
                    ->orWhereRaw("obligation.source_ref != CONCAT('{$sourcePrefix}', source.id)");
            })
            ->exists();
        if ($invalidPointer) {
            throw new RuntimeException(
                "Cannot retire {$table}.finance_charge_id: each source pointer must agree with its canonical academic Finance obligation."
            );
        }

        Schema::table($table, function (Blueprint $schema): void {
            $schema->dropForeign(['finance_charge_id']);
            $schema->dropIndex(['finance_charge_id']);
            $schema->dropColumn('finance_charge_id');
        });
    }

    /** Forward-only retirement: Academic source rows must not regain ledger pointers. */
    public function down(): void {}
};
