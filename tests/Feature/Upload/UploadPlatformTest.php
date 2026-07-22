<?php

declare(strict_types=1);

use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Upload\Policies\UploadRecordPolicy;
use App\Modules\Upload\Support\ChunkedUploadManager;
use App\Modules\Upload\Support\UploadActor;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Auth\Access\AuthorizationException;

it('resolves upload, URL, validation, and chunked operations through one platform boundary', function () {
    expect(app(UploadPlatform::class))->toBeInstanceOf(UploadPlatform::class);
});

it('keeps the legacy uploads route prefix and route names', function () {
    expect(route('uploads.serve', ['id' => 42]))->toContain('/api/uploads/serve/42');
});

it('serves the existing public upload configuration through the platform controller', function () {
    $this->getJson(route('uploads.config'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['contexts', 'defaults', 'security', 'performance'],
        ]);
});

it('preserves the legacy multiple-upload validation envelope', function () {
    $this->withoutMiddleware()
        ->postJson(route('uploads.upload-multiple'), [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Multiple upload failed: validation.required')
        ->assertJsonStructure(['errors']);
});

it('preserves the legacy list-validation envelope and status', function () {
    $this->withoutMiddleware()
        ->getJson(route('uploads.index', ['per_page' => 0]))
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Failed to retrieve uploads')
        ->assertJsonStructure(['errors']);
});

it('preserves the legacy student-avatar validation envelope', function () {
    $this->withoutMiddleware()
        ->postJson(route('uploads.student-avatar', ['studentId' => 999]), [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['file']]);
});

it('authorizes an upload owner through the registered policy', function () {
    $user = User::factory()->make(['id' => 42]);
    $upload = UploadRecord::factory()->make(['user_id' => 42]);
    $policy = app(UploadRecordPolicy::class);

    expect($policy->view($user, $upload))->toBeTrue()
        ->and($policy->delete($user, $upload))->toBeTrue();
});

it('prevents another actor from inspecting a chunked upload session', function () {
    $manager = app(ChunkedUploadManager::class);
    $session = $manager->initializeUpload('private.pdf', 1, 'general', userId: 1);

    expect(fn () => $manager->getUploadStatus($session['upload_id'], new UploadActor(userId: 2)))
        ->toThrow(AuthorizationException::class);

    expect($manager->cancelUpload($session['upload_id'], new UploadActor(userId: 1)))->toBeTrue();
});
