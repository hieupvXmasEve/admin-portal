<?php

declare(strict_types=1);

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
        Schema::create('guardian_access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guardian_relationship_id')->unique();
            $table->foreignId('parent_id')->unique()->constrained('parents')->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('read_only');
            $table->string('status')->default('active');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('guardian_relationship_id', 'guardian_access_relationship_foreign')
                ->references('id')
                ->on('student_guardian_relationships')
                ->cascadeOnDelete();
            $table->index(['student_id', 'status']);
        });

        DB::table('parent_student')
            ->join('student_guardian_relationships', 'student_guardian_relationships.legacy_parent_student_id', '=', 'parent_student.id')
            ->select([
                'parent_student.id as legacy_parent_student_id',
                'parent_student.parent_id',
                'parent_student.student_id',
                'parent_student.access_level',
                'parent_student.created_at',
                'parent_student.updated_at',
                'student_guardian_relationships.id as guardian_relationship_id',
            ])
            ->orderBy('parent_student.id')
            ->chunkById(200, function ($links): void {
                foreach ($links as $link) {
                    DB::table('guardian_access_grants')->insertOrIgnore([
                        'guardian_relationship_id' => $link->guardian_relationship_id,
                        'parent_id' => $link->parent_id,
                        'student_id' => $link->student_id,
                        'access_level' => $link->access_level,
                        'status' => 'active',
                        'granted_at' => $link->created_at ?? now(),
                        'created_at' => $link->created_at,
                        'updated_at' => $link->updated_at,
                    ]);
                }
            }, 'parent_student.id', 'legacy_parent_student_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_access_grants');
    }
};
