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
        Schema::create('student_guardian_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_application_guardian_id')->nullable();
            $table->unsignedBigInteger('legacy_parent_student_id')->nullable()->unique();
            $table->string('full_name');
            $table->string('relationship_type')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_guardian_key')
                ->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN student_id ELSE NULL END');
            $table->timestamps();

            $table->unique('primary_guardian_key', 'student_guardian_one_primary_unique');
            $table->unique('source_application_guardian_id', 'student_guardian_source_unique');
            $table->foreign('source_application_guardian_id', 'student_guardian_source_foreign')
                ->references('id')
                ->on('application_guardians')
                ->nullOnDelete();
            $table->index(['student_id', 'is_primary']);
        });

        DB::table('application_guardians')
            ->join('student_applications', 'student_applications.id', '=', 'application_guardians.student_application_id')
            ->whereNotNull('student_applications.student_id')
            ->select([
                'application_guardians.id as source_application_guardian_id',
                'student_applications.student_id',
                'application_guardians.full_name',
                'application_guardians.relationship',
                'application_guardians.phone',
                'application_guardians.email',
                'application_guardians.occupation',
                'application_guardians.address',
                'application_guardians.is_primary',
                'application_guardians.created_at',
                'application_guardians.updated_at',
            ])
            ->orderBy('application_guardians.id')
            ->chunkById(200, function ($guardians): void {
                foreach ($guardians as $guardian) {
                    DB::table('student_guardian_relationships')->insert([
                        'student_id' => $guardian->student_id,
                        'source_application_guardian_id' => $guardian->source_application_guardian_id,
                        'full_name' => $guardian->full_name,
                        'relationship_type' => $guardian->relationship,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        'occupation' => $guardian->occupation,
                        'address' => $guardian->address,
                        'is_primary' => $guardian->is_primary,
                        'created_at' => $guardian->created_at,
                        'updated_at' => $guardian->updated_at,
                    ]);
                }
            }, 'application_guardians.id', 'source_application_guardian_id');

        DB::table('parent_student')
            ->join('parents', 'parents.id', '=', 'parent_student.parent_id')
            ->select([
                'parent_student.id as legacy_parent_student_id',
                'parent_student.student_id',
                'parent_student.relationship',
                'parent_student.is_primary',
                'parent_student.created_at',
                'parent_student.updated_at',
                'parents.full_name',
                'parents.phone',
                'parents.email_snapshot',
            ])
            ->orderBy('parent_student.id')
            ->chunkById(200, function ($links): void {
                foreach ($links as $link) {
                    $applicationRelationshipId = $link->email_snapshot !== null
                        ? DB::table('student_guardian_relationships')
                            ->where('student_id', $link->student_id)
                            ->whereRaw('LOWER(email) = ?', [strtolower((string) $link->email_snapshot)])
                            ->value('id')
                        : null;

                    if ($applicationRelationshipId !== null) {
                        DB::table('student_guardian_relationships')
                            ->where('id', $applicationRelationshipId)
                            ->update(['legacy_parent_student_id' => $link->legacy_parent_student_id]);

                        continue;
                    }

                    $isPrimary = (bool) $link->is_primary
                        && ! DB::table('student_guardian_relationships')
                            ->where('student_id', $link->student_id)
                            ->where('is_primary', true)
                            ->exists();

                    DB::table('student_guardian_relationships')->insert([
                        'student_id' => $link->student_id,
                        'legacy_parent_student_id' => $link->legacy_parent_student_id,
                        'full_name' => $link->full_name,
                        'relationship_type' => $link->relationship,
                        'phone' => $link->phone,
                        'email' => $link->email_snapshot,
                        'is_primary' => $isPrimary,
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
        Schema::dropIfExists('student_guardian_relationships');
    }
};
