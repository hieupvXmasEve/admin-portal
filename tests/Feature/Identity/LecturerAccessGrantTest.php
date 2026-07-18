<?php

declare(strict_types=1);

use App\Models\Lecture;
use App\Models\User;
use App\Modules\Academic\FacultyWorkforce\Actions\ReconcileLecturerAccessEligibilityAction;
use App\Policies\ApiActorPolicy;
use App\Shared\Contracts\Identity\DTO\FacultyAccessEligibility;
use App\Shared\Contracts\Identity\LecturerAccessGrantWriter;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('authorizes lecturer login from the Identity access grant rather than workforce employment fields', function (): void {
    $user = User::factory()->create([
        'email' => 'lecturer-access@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'employment_status' => 'terminated',
        'is_active' => false,
    ]);

    DB::table('lecturer_access_grants')
        ->where('user_id', $user->id)
        ->update([
            'status' => 'active',
            'reason' => 'eligible_active_employment',
            'granted_at' => now(),
            'revoked_at' => null,
            'updated_at' => now(),
        ]);

    $this->postJson(route('api.lecturer.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_in', 8 * 60);
});

it('synchronously revokes lecturer access and existing tokens when Workforce terminates employment', function (): void {
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('existing-lecturer-session', ['lecturer:access']);

    $lecturer->update(['employment_status' => 'terminated']);

    expect(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('revoked')
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse()
        ->and(DB::table('faculty_access_eligibility_outbox')
            ->where('lecturer_id', $lecturer->id)
            ->where('status', 'dispatched')
            ->count())->toBeGreaterThan(0)
        ->and(DB::table('lecturer_access_eligibility_handoffs')
            ->where('lecturer_id', $lecturer->id)
            ->where('reason', 'ineligible_employment_status')
            ->count())->toBe(1);
});

it('revokes the previous account before relinking lecturer access to a new account', function (): void {
    $previousUser = User::factory()->create(['type' => UserType::LECTURER, 'status' => User::STATUS_ACTIVE]);
    $nextUser = User::factory()->create(['type' => UserType::LECTURER, 'status' => User::STATUS_ACTIVE]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $previousUser->id,
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('previous-account-session', ['lecturer:access']);

    $lecturer->update(['user_id' => $nextUser->id]);

    expect(DB::table('lecturer_access_grants')->where('user_id', $previousUser->id)->exists())->toBeFalse()
        ->and(DB::table('lecturer_access_grants')->where('user_id', $nextUser->id)->value('status'))->toBe('active')
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
});

it('preserves lecturer access during leave and sabbatical by default', function (): void {
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('leave-session', ['lecturer:access']);

    $lecturer->update(['employment_status' => 'on_leave']);

    expect(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('active')
        ->and(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('reason'))->toBe('eligible_leave')
        ->and(app(ApiActorPolicy::class)->accessLecturer($lecturer->fresh(), Request::create('/api/v1/lecturer/auth/refresh')))->toBeTrue()
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeTrue();

    $lecturer->update(['employment_status' => 'sabbatical']);

    expect(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('active')
        ->and(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('reason'))->toBe('eligible_sabbatical')
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeTrue();
});

it('synchronously revokes access when a contract expires', function (): void {
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
        'employment_type' => 'contract',
        'contract_end_date' => today()->addDay(),
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('contract-session', ['lecturer:access']);

    $lecturer->update(['contract_end_date' => today()->subDay()]);

    expect(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('revoked')
        ->and(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('reason'))->toBe('ineligible_contract_expired')
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
});

it('records an eligibility handoff once and does not repeat revocation during replay', function (): void {
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('terminated-session', ['lecturer:access']);

    $lecturer->update(['employment_status' => 'terminated']);
    $handoff = DB::table('lecturer_access_eligibility_handoffs')
        ->where('lecturer_id', $lecturer->id)
        ->where('reason', 'ineligible_employment_status')
        ->first();
    $replacementToken = $lecturer->createToken('replayed-session', ['lecturer:access']);

    app(LecturerAccessGrantWriter::class)->apply(new FacultyAccessEligibility(
        lecturerId: $lecturer->id,
        userId: $user->id,
        tokenSubjectType: $lecturer->getMorphClass(),
        isEligible: false,
        reason: 'ineligible_employment_status',
        deduplicationKey: $handoff->deduplication_key,
        evaluatedAt: now()->toISOString(),
    ));

    expect(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('id', $replacementToken->accessToken->id)->exists())->toBeTrue()
        ->and(DB::table('lecturer_access_eligibility_handoffs')
            ->where('deduplication_key', $handoff->deduplication_key)
            ->count())->toBe(1);
});

it('reconciles a missed Workforce eligibility handoff without relying on Identity reads', function (): void {
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $lecturer = Lecture::factory()->create([
        'user_id' => $user->id,
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $token = $lecturer->createToken('missed-handoff-session', ['lecturer:access']);

    DB::table('lectures')->where('id', $lecturer->id)->update([
        'employment_status' => 'suspended',
        'updated_at' => now(),
    ]);

    expect(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('active');

    expect(ReconcileLecturerAccessEligibilityAction::run(['lecturer_id' => $lecturer->id]))->toBe(1)
        ->and(DB::table('lecturer_access_grants')->where('user_id', $user->id)->value('status'))->toBe('revoked')
        ->and(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
});
