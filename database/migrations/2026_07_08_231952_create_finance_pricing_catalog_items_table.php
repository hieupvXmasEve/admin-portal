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
        Schema::create('finance_pricing_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('obligation_type', 50);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('rule_version', 100);
            $table->string('description', 255)->nullable();
            $table->json('facts_match')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['obligation_type', 'rule_version'], 'finance_pricing_catalog_type_rule_unique');
            $table->index(['obligation_type', 'is_active'], 'finance_pricing_catalog_type_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_pricing_catalog_items');
    }
};
