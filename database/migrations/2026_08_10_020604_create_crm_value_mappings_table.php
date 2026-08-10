<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `kind` is a BE-validated allow-list (campus, major, specialization, intake,
 * scholarship, pathway_gateway, uu_dai_gc), not a DB enum. The target intake
 * lives here as `kind='intake', crm_value='__default__'` rather than in
 * `system_settings`, which is Platform-owned behind a closed key registry.
 */
return new class extends Migration
{
    /**
     * Snapshot of App\Services\Admissions\IntendedProgramNormalizer::LABEL_TO_CODE
     * at the time this migration was written. Inlined rather than imported —
     * a migration must stay runnable (`migrate:fresh`) even after that class
     * is renamed or retired; if the source list changes, add a mapping row
     * through the app instead of editing history here.
     *
     * @var array<string, string>
     */
    private const SEEDED_MAJOR_LABELS = [
        'Công nghệ bán dẫn' => 'SEMI',
        'Trí tuệ nhân tạo' => 'AI',
        'Tài chính' => 'FIN',
        'Quản trị kinh doanh' => 'BA',
    ];

    public function up(): void
    {
        Schema::create('crm_value_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('crm_value');
            $table->string('local_code')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'crm_value']);
        });

        $now = now();
        $rows = [];
        foreach (self::SEEDED_MAJOR_LABELS as $label => $code) {
            $rows[] = [
                'kind' => 'major',
                'crm_value' => $label,
                'local_code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('crm_value_mappings')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_value_mappings');
    }
};
