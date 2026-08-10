<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('crm_campus')->nullable()->after('campus_code');
            $table->string('crm_major')->nullable()->after('intended_program');
            $table->string('province')->nullable();
            $table->string('new_province')->nullable();
            $table->string('new_street')->nullable();
            $table->string('new_ward')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('nationality')->nullable();
            $table->string('religion')->nullable();
            $table->string('id_card_place_of_issue')->nullable();
            $table->string('school')->nullable();
            $table->string('graduation_year', 10)->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('gpa_type')->nullable();
            $table->string('scholarship')->nullable();
            $table->string('pathway_gateway')->nullable();
            $table->string('uu_dai_gc')->nullable();
            $table->decimal('crm_paid_amount', 15, 2)->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropColumn([
                'crm_campus',
                'crm_major',
                'province',
                'new_province',
                'new_street',
                'new_ward',
                'permanent_address',
                'birth_place',
                'nationality',
                'religion',
                'id_card_place_of_issue',
                'school',
                'graduation_year',
                'gpa',
                'gpa_type',
                'scholarship',
                'pathway_gateway',
                'uu_dai_gc',
                'crm_paid_amount',
                'last_synced_at',
            ]);
        });
    }
};
