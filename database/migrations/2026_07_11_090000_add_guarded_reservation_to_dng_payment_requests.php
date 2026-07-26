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
        if (! Schema::hasColumn('billing_accounts', 'settlement_version')) {
            Schema::table('billing_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('settlement_version')->default(0)->after('student_id');
            });
        }

        Schema::table('dng_payment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('dng_payment_requests', 'billing_account_id')) {
                $table->foreignId('billing_account_id')->nullable()->after('student_id')
                    ->constrained('billing_accounts')->restrictOnDelete();
            }
            if (! Schema::hasColumn('dng_payment_requests', 'provider_rail')) {
                $table->string('provider_rail', 32)->nullable()->after('campus_code');
            }
            if (! Schema::hasColumn('dng_payment_requests', 'active_slot_key')) {
                $table->string('active_slot_key', 128)->nullable()->unique()->after('item_id');
            }
            if (! Schema::hasColumn('dng_payment_requests', 'captured_settlement_version')) {
                $table->unsignedBigInteger('captured_settlement_version')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('dng_payment_requests', 'target_fingerprint')) {
                $table->string('target_fingerprint', 64)->nullable()->after('captured_settlement_version');
            }
            if (! Schema::hasColumn('dng_payment_requests', 'reserved_at')) {
                $table->timestamp('reserved_at')->nullable()->after('target_fingerprint');
            }
        });

        if (! Schema::hasTable('dng_payment_request_reservation_targets')) {
            Schema::create('dng_payment_request_reservation_targets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('dng_payment_request_id');
                $table->foreign('dng_payment_request_id', 'dng_rsv_target_request_fk')
                    ->references('id')->on('dng_payment_requests')->cascadeOnDelete();
                $table->unsignedBigInteger('invoice_line_id');
                $table->foreign('invoice_line_id', 'dng_rsv_target_line_fk')
                    ->references('id')->on('invoice_lines')->restrictOnDelete();
                $table->unsignedBigInteger('finance_charge_installment_id')->nullable();
                $table->foreign('finance_charge_installment_id', 'dng_rsv_target_installment_fk')
                    ->references('id')->on('finance_charge_installments')->nullOnDelete();
                $table->decimal('captured_collectible', 15, 2);
                $table->string('target_identity', 128);
                $table->timestamps();

                $table->unique(['dng_payment_request_id', 'target_identity'], 'dng_reservation_target_unique');
                $table->index('invoice_line_id', 'dng_reservation_target_line_idx');
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $dropCheck = DB::getDriverName() === 'mariadb' ? 'DROP CONSTRAINT' : 'DROP CHECK';
            DB::statement("ALTER TABLE `dng_payment_requests` {$dropCheck} `chk_dng_pr_status`");
            DB::statement("ALTER TABLE `dng_payment_requests` ADD CONSTRAINT `chk_dng_pr_status` CHECK (`status` IN ('pending','pushed_to_dng','paid_uninvoiced','paid_invoiced','reconciled','failed','cancelled','cancel_pushed_to_dng','unknown_outcome','needs_review'))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $dropCheck = DB::getDriverName() === 'mariadb' ? 'DROP CONSTRAINT' : 'DROP CHECK';
            DB::statement("ALTER TABLE `dng_payment_requests` {$dropCheck} `chk_dng_pr_status`");
            DB::statement("ALTER TABLE `dng_payment_requests` ADD CONSTRAINT `chk_dng_pr_status` CHECK (`status` IN ('pending','pushed_to_dng','paid_uninvoiced','paid_invoiced','reconciled','failed','cancelled','cancel_pushed_to_dng'))");
        }

        Schema::dropIfExists('dng_payment_request_reservation_targets');
        Schema::table('dng_payment_requests', function (Blueprint $table): void {
            $table->dropUnique(['active_slot_key']);
            $table->dropConstrainedForeignId('billing_account_id');
            $table->dropColumn(['provider_rail', 'active_slot_key', 'captured_settlement_version', 'target_fingerprint', 'reserved_at']);
        });
        Schema::table('billing_accounts', function (Blueprint $table): void {
            $table->dropColumn('settlement_version');
        });
    }
};
