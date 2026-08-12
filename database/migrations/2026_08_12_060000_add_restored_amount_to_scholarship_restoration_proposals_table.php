<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_restoration_proposals', function (Blueprint $table): void {
            // Same unit convention as scholarship_semester_adjustments.adjusted_amount
            // (value of the adjustment's original_type — "remaining value", not a
            // deduction). NULL = full restore (today's behavior, backward compatible).
            $table->decimal('restored_amount', 12, 2)->nullable()->after('evaluated_semester_id');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_restoration_proposals', function (Blueprint $table): void {
            $table->dropColumn('restored_amount');
        });
    }
};
