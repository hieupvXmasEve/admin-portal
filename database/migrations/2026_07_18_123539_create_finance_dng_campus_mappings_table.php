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
        $duplicates = DB::table('campuses')
            ->selectRaw('TRIM(dng_code) as provider_code')
            ->whereNotNull('dng_code')
            ->whereRaw("TRIM(dng_code) <> ''")
            ->groupByRaw('TRIM(dng_code)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('provider_code')
            ->all();

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Cannot migrate DNG campus mappings because these legacy campuses.dng_code values are duplicated: '
                .implode(', ', $duplicates)
                .'. Resolve the duplicate provider codes and run the migration again.',
            );
        }

        if (! Schema::hasTable('finance_dng_campus_mappings')) {
            Schema::create('finance_dng_campus_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->constrained()->restrictOnDelete();
                $table->string('provider_code', 255);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('campus_id');
                $table->unique('provider_code');
            });
        }

        $now = now();
        DB::table('campuses')
            ->select(['id', 'dng_code'])
            ->whereNotNull('dng_code')
            ->whereRaw("TRIM(dng_code) <> ''")
            ->orderBy('id')
            ->each(function (object $campus) use ($now): void {
                $exists = DB::table('finance_dng_campus_mappings')
                    ->where('campus_id', $campus->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('finance_dng_campus_mappings')->insert([
                    'campus_id' => $campus->id,
                    'provider_code' => trim((string) $campus->dng_code),
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_dng_campus_mappings');
    }
};
