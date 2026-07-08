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
        Schema::create('finance_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 50);
            $table->string('source_kind', 100);
            $table->string('source_ref', 120);
            $table->string('obligation_type', 50);
            $table->enum('lifecycle_status', [
                'requested',
                'accepted',
                'rejected',
                'cancelled',
                'voided',
                'superseded',
            ])->default('requested');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('pricing_rule_version', 100);
            $table->json('pricing_snapshot');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'source_kind', 'source_ref', 'obligation_type'],
                'finance_obligations_source_quad_unique'
            );
            $table->index(['obligation_type', 'lifecycle_status'], 'finance_obligations_type_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_obligations');
    }
};
