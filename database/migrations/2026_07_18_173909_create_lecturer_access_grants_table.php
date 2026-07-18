<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lecturer_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            // The Faculty Workforce identifier is deliberately opaque to Identity.
            $table->unsignedBigInteger('lecturer_id')->unique();
            $table->string('token_subject_type', 120);
            $table->string('status', 20);
            $table->string('reason', 80);
            $table->timestamp('eligibility_evaluated_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id']);
        });

        Schema::create('lecturer_access_eligibility_handoffs', function (Blueprint $table) {
            $table->id();
            $table->string('deduplication_key', 120)->unique();
            $table->unsignedBigInteger('lecturer_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('eligibility_status', 20);
            $table->string('reason', 80);
            $table->unsignedInteger('revoked_token_count')->default(0);
            $table->json('payload');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['lecturer_id', 'applied_at'], 'lecturer_access_handoff_lookup_idx');
        });

        $now = now();
        $today = $now->copy()->startOfDay();

        DB::table('lectures')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($lecturers) use ($now, $today): void {
                foreach ($lecturers as $lecturer) {
                    $status = strtolower(trim((string) $lecturer->employment_status));
                    $isContractExpired = $lecturer->employment_type === 'contract'
                        && $lecturer->contract_end_date !== null
                        && CarbonImmutable::parse($lecturer->contract_end_date)->lessThanOrEqualTo($today);
                    $isActive = (bool) $lecturer->is_active;
                    $grantStatus = $isActive
                        && ! $isContractExpired
                        && in_array($status, ['active', 'employed', 'contract_active', 'on_leave', 'sabbatical'], true)
                        ? 'active'
                        : 'revoked';
                    $reason = ! $isActive
                        ? 'ineligible_faculty_inactive'
                        : ($isContractExpired
                            ? 'ineligible_contract_expired'
                            : match ($status) {
                                'active', 'employed', 'contract_active' => 'eligible_active_employment',
                                'on_leave' => 'eligible_leave',
                                'sabbatical' => 'eligible_sabbatical',
                                default => 'ineligible_employment_status',
                            });

                    DB::table('lecturer_access_grants')->insert([
                        'user_id' => $lecturer->user_id,
                        'lecturer_id' => $lecturer->id,
                        'token_subject_type' => 'lecture',
                        'status' => $grantStatus,
                        'reason' => $reason,
                        'eligibility_evaluated_at' => $now,
                        'granted_at' => $grantStatus === 'active' ? $now : null,
                        'revoked_at' => $grantStatus === 'revoked' ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('lecturer_access_eligibility_handoffs')->insert([
                        'deduplication_key' => 'migration-backfill:'.$lecturer->id,
                        'lecturer_id' => $lecturer->id,
                        'user_id' => $lecturer->user_id,
                        'eligibility_status' => $grantStatus,
                        'reason' => $reason,
                        'revoked_token_count' => 0,
                        'payload' => json_encode(['source' => 'migration_backfill'], JSON_THROW_ON_ERROR),
                        'applied_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturer_access_eligibility_handoffs');
        Schema::dropIfExists('lecturer_access_grants');
    }
};
