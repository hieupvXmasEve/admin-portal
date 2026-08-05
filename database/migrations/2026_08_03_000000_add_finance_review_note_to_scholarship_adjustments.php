<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why an approved adjustment stopped short of the ledger.
 *
 * Finance already knows the reason (invoice already paid, DNG activity in
 * flight, …) but it was only ever returned in-memory and dropped, so both
 * offices saw "needs finance review" with no explanation. The note is stored
 * on BOTH sides on purpose: Academic may not read a Finance model (module
 * boundary), so it keeps its own copy of the sentence it has to display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_semester_adjustments', function (Blueprint $table): void {
            $table->text('review_note')->nullable()->after('status');
        });

        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->text('finance_review_note')->nullable()->after('decision_reason');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_semester_adjustments', function (Blueprint $table): void {
            $table->dropColumn('review_note');
        });

        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->dropColumn('finance_review_note');
        });
    }
};
