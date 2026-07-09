<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('finance_obligations', function (Blueprint $table) {
            // Nullable: pre-student obligations (wave 6) and legacy rows before backfill.
            $table->foreignId('billing_account_id')
                ->nullable()
                ->after('id')
                ->constrained('billing_accounts')
                ->restrictOnDelete();

            $table->index('billing_account_id', 'finance_obligations_billing_account_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_obligations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_account_id');
        });
    }
};
