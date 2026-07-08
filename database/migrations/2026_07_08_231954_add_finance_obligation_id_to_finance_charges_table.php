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
        Schema::table('finance_charges', function (Blueprint $table) {
            $table->foreignId('finance_obligation_id')
                ->nullable()
                ->after('id')
                ->constrained('finance_obligations')
                ->nullOnDelete();

            $table->index('finance_obligation_id', 'finance_charges_obligation_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_charges', function (Blueprint $table) {
            $table->dropForeign(['finance_obligation_id']);
            $table->dropIndex('finance_charges_obligation_id_idx');
            $table->dropColumn('finance_obligation_id');
        });
    }
};
