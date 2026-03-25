<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_discounts', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_discounts', 'status')) {
                $table->enum('status', ['active', 'reversed', 'expired'])->default('active')->after('amount');
            }

            if (! Schema::hasColumn('invoice_discounts', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('invoice_discounts', 'memo')) {
                $table->text('memo')->nullable()->after('approved_by');
            }

            if (! $this->hasIndex('invoice_discounts', 'invoice_discounts_invoice_id_status_index')) {
                $table->index(['invoice_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_discounts', function (Blueprint $table) {
            if ($this->hasIndex('invoice_discounts', 'invoice_discounts_invoice_id_status_index')) {
                $table->dropIndex(['invoice_id', 'status']);
            }

            if (Schema::hasColumn('invoice_discounts', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('invoice_discounts', 'status') ? 'status' : null,
                Schema::hasColumn('invoice_discounts', 'memo') ? 'memo' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return \Illuminate\Support\Facades\DB::table('information_schema.statistics')
            ->where('table_schema', \Illuminate\Support\Facades\DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
